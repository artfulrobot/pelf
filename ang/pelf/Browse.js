(function(angular, $, _) {

  angular.module('pelf').config(function($routeProvider) {
      $routeProvider.when('/pelf/browse', {
        controller: 'PelfBrowse',
        templateUrl: '~/pelf/BrowseAll.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        // artfulrobot: we leave this for now otherwise the user is left wondering what is going on while it loads.
        // resolve: {
        //   myContact: function(crmApi) {
        //     return crmApi('Contact', 'getsingle', {
        //       id: 'user_contact_id',
        //       return: ['first_name', 'last_name']
        //     });
        //   }
        // }
      });
      $routeProvider.when('/pelf/browse/type/:case_type', {
        controller: 'PelfBrowse',
        templateUrl: '~/pelf/Browse.html',
      });
      $routeProvider.when('/pelf/venture/:case_id', {
        controller: 'PelfVenture',
        templateUrl: '~/pelf/Venture.html',
      });
    }
  );

  angular.module('pelf').controller('PelfBrowse', function($scope, crmApi, crmStatus, crmUiHelp, $routeParams) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('pelf');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/pelf/Browse'}); // See: templates/CRM/pelf/Browse.hlp

    $scope.state = 'loading';
    $scope.initialLoad = true; // Flag so we can set up defaults that depend on some of the data.
    $scope.listDetails = 'activity';
    $scope.sortedCases = [];
    $scope.cases = {};
    $scope.crmURL = CRM.url;
    $scope.filters = {
      sort: 'Status',
      projects: [],
      phases: [],
      status: [],
      years: [],
      adjusted: true
    };
    $scope.projectsFilterOptions = {results:[]};
    $scope.yearsFilterOptions = {results:[]};
    $scope.statusFilterOptions = {results:[]};
    $scope.phaseFilterOptions = {results:[
      {id: 'prospect', text: 'Prospect'},
      {id: 'live', text: 'Live'},
      {id: 'complete', text: 'Completed'},
      {id: 'dropped', text: 'Dropped (by us)'},
      {id: 'failed', text: 'Failed (rejected)'},
    ]};
    $scope.projectPivot = [];
    $scope.projects = [];
    $scope.pageTitle = 'Pelf: All Cases';
    $scope.$watch('filters', applySortAndFilter, true);
    $scope.totals = { adjusted: 0, total: 0 };
    $scope.filteredFunds = [];
    $scope.currencySymbol = '';
    $scope.toggleUrgent = function toggleUrgent(venture) {
      if (!venture.activityLast) {
        return false; // weird, they should not have seen this to click on.
      }
      const params = {
        id: venture.activityNext.id,
        priority_id: (venture.activityNext.urgent == 1) ? 2 : 1,
      };
      if (!(params.id > 0)) {
        console.warn("toggleUrgent failed on ", venture, "with params", params);
        return false;
      }
      // Optimistic change.
      venture.activityNext.urgent = (venture.activityNext.urgent == 1) ? 0 : 1;

      crmApi('Activity', 'create', params)
      .then(r => {}, handleFail);

      return false;
    };

    /**
     * Recreates sortedCases and filteredFunds arrays according to the user
     * selections.
     */
    function applySortAndFilter() {
      $scope.sortedCases = [];
      var fundAllocations = [];
      if (!$scope.cases) {
        return;
      }

      const needToFilterOnStatus = $scope.filters.status.length > 0;
      const needToFilterOnYears = $scope.filters.years.length > 0;
      const needToFilterOnProjects = $scope.filters.projects.length > 0;
      const needToFilterOnPhases = $scope.filters.phases.length > 0;
      const { status: statusValues,  phases: phaseValues, years: yearValues, projects: projectValues } = $scope.filters;

      for (const key in $scope.cases) {
        if (!$scope.cases.hasOwnProperty(key)) {
          continue;
        }
        var item = $scope.cases[key];
        var anyMatch = false;

        // Determine if this *case* matches our filters.

        // Status match?
        if (needToFilterOnStatus && statusValues.indexOf(item.status_id.toString()) == -1) {
          // No.
          continue;
        }
        // Phase match
        if (needToFilterOnPhases && phaseValues.indexOf($scope.case_statuses[item.status_id].phase) == -1) {
          // No.
          continue;
        }

        // Project and year matches work on the fundAllocations
        // For selecting cases, we only need a single fund allocation to match
        // to allow the case through.
        // For selecting funds, we filter out all other things that don't match.

        var projectAndYearMatches = !(needToFilterOnProjects || needToFilterOnYears);
        (item.funds || []).forEach(row => {

          // Ignore rows that do not match.
          if (needToFilterOnProjects && projectValues.indexOf(row.project) === -1) {
            return;
          }
          if (needToFilterOnYears && yearValues.indexOf(row.fy_start) === -1) {
            return;
          }

          // This row matches.
          projectAndYearMatches = true;
          fundAllocations.push(row);
        });
        if (!projectAndYearMatches) {
          // Discard a case that does not have any fundAllocations matching
          // on required project/year values.
          continue;
        }

        $scope.sortedCases.push($scope.cases[key]);
      }
      $scope.filteredFunds = fundAllocations;

      if ($scope.filters.sort == 'Status') {
        $scope.sortedCases.sort((a, b) => {
          return parseInt($scope.case_statuses[a.status_id].weight) - parseInt($scope.case_statuses[b.status_id].weight);
        });
      }
      else if ($scope.filters.sort == 'Worth') {
        $scope.sortedCases.sort((a, b) => {
          return b.funds_total - a.funds_total;
        });
      }
      else if ($scope.filters.sort == 'Adjusted Worth') {
        $scope.sortedCases.sort((a, b) => {
          return b.funds_total * b.worth_percent / 100 - a.funds_total * a.worth_percent / 100;
        });
      }
      else if ($scope.filters.sort == 'Next') {
        $scope.sortedCases.sort((a, b) => {
          // First, shift ones with nothing scheduled to the end.
          var an = a.activityNext, bn = b.activityNext;
          if (!an && !bn) {
            return 0;
          }
          if (!an && bn) {
            return 1; // a should go beneith
          }
          if (an && !bn) {
            return -1; // a should go above
          }
          // Both have activities, sort by date, descending.
          if (an.activity_date_time < bn.activity_date_time) {
            return -1;
          }
          if (an.activity_date_time > bn.activity_date_time) {
            return 1;
          }
          return 0;
        });
      }
    }

    var numberFormatter = Intl.NumberFormat();
    $scope.pelfMoney = function(amount, item) {
      if (!amount) return '';
      amount = adjustAmount(amount, item);
      var unit = '';
      // I'm not sure about this.
      // if we do it here we should do it everywhere.
      if (false && amount >= 1000) {
        unit = 'k';
        amount /= 1000;
      }
      amount = numberFormatter.format(Math.round(amount));
      amount = $scope.currencySymbol + amount + unit;
      return amount;
    };
    function adjustAmount(amount, item) {
      if (!amount) return '';
      if ($scope.filters.adjusted) {
        amount *= item.worth_percent / 100;
      }
      return Math.round(amount);
    }

    // This is called when the data is loaded from CiviCRM.
    function updateData(r) {
      r = r.values;
      console.log("updateData", r);
      const civiRoot = CRM.url('civicrm/a');
      _.each(r.cases, venture => {
        // Determine the Case Url.
        //
        // This is Civi's manage Case link:
        venture.manageUrl = CRM.url('civicrm/contact/view/case', {
          reset: 1,
          id: venture.id,
          cid: venture.clients[0], // First (probably only) client
          action: 'view',
          context: 'search',
          selectedChild: 'case'
        });
        venture.ventureUrl = civiRoot + '#pelf/venture/' + venture.id;

        if (venture.activityNext) {
          venture.activityNext.soon = '';
          venture.dateObject = new Date(venture.activityNext.activity_date_time);

          var diff = new Date(venture.activityNext.activity_date_time);
          diff.setHours(0);
          diff.setMinutes(0);
          diff.setSeconds(0);
          var n = new Date();
          n.setHours(0);
          n.setMinutes(0);
          n.setSeconds(0);

          diff = Math.round((diff - n)/1000/60/60/24);
          if (diff <= 1) {
            venture.activityNext.soon = 'Today';
          }
          else if (diff <= 2) {
            venture.activityNext.soon = 'Tomorrow';
          }
          else if (diff < 7) {
            venture.activityNext.soon = 'In ' + Math.floor(diff) + ' days';
          }

        }
      });
      $scope.currencySymbol = r.currencySymbol;
      console.log("Set currencySymbol", $scope.currencySymbol);
      $scope.cases = r.cases;
      $scope.clients = r.clients;
      $scope.state = 'loaded';

      $scope.projects = r.projects;
      $scope.pivotStatus = r.pivot_status;
      $scope.pivotProjects = r.pivot_projects;
      $scope.totals = r.totals;
      $scope.projectsFilterOptions.results = [];
      for (const key in r.projects) {
        if (r.projects.hasOwnProperty(key)) {
          $scope.projectsFilterOptions.results.push({id: (r.projects[key].value), text: r.projects[key].label });
        }
      }

      $scope.financial_years = r.financial_years;
      $scope.yearsFilterOptions.results = r.financial_years
        .map(yearStart => {
          var text = parseInt(yearStart.substr(0, 4));
          if (yearStart.substr(-5) !== '01-01') {
            text = `${text}-${text+1}`;
          }
          return {id: yearStart, text};
        })
        .sort((a,b) => ( (a.id < b.id) ? 1 : (a.id > b.id) ? -1 : 0 ));
      if ($scope.initialLoad) {
        // This is the first time we've had access to the data.

        // Set the default financial year filter to be this year and next.
        var today = (new Date()).toISOString().substr(0, 10);
        for (var i = $scope.yearsFilterOptions.results.length - 1; i>0; i--) {
          var aFy = $scope.yearsFilterOptions.results[i].id;
          $scope.filters.years.push(aFy);
          if (aFy > today) break;
          if ($scope.filters.years.length === 2) {
            $scope.filters.years.shift();
          }
        }

        // Don't always do this.
        delete $scope.initialLoad;
      }

      $scope.case_statuses = r.case_statuses;
      $scope.sorted_case_statuses= CRM._.sortBy(CRM._.values(r.case_statuses), s => parseInt(s.weight));

      // Each status has:
      // - value (internal string name)
      // - label (user facing text)
      // - whether it's a Opened/Closed (from CiviCase) - inaccessible to us, but that's ok because we have...
      // - which Pelf 'phase' it belongs to: prospect/live (Opened phases) or completed/failed/dropped (Closed phases)
      $scope.statusFilterOptions.results = Object.keys(r.case_statuses).map(s => ({id: r.case_statuses[s].value, text: r.case_statuses[s].label}));

      $scope.pageTitle = 'Pelf: All Cases';
      $scope.exportUrl = CRM.url('civicrm/pelf/export', {caseType: $routeParams.case_type || ''});
      if ($routeParams.case_type) {
        if (r.caseTypes[parseInt($routeParams.case_type)]) {
          $scope.pageTitle = 'Pelf: ' + r.caseTypes[parseInt($routeParams.case_type)].title + ' cases';
        }
      }

      applySortAndFilter();
    }
    function handleFail(r) {
      console.log("handleFail", r);
      alert(r.error_message);
      return;
    }
    const reload = function reload() {
      $scope.state = 'loading';
      params = Object.assign({}, $scope.filters);

      // Case Type match?
      if ($routeParams.case_type) {
        params.case_type_id = $routeParams.case_type;
      }
      crmApi('Pelf', 'getbrowse', params).then(updateData, handleFail);
    };
    $scope.reload = reload;

    reload();
  });


  angular.module('pelf').directive('pelfActivityCard', function() {
    return {
      templateUrl: '~/pelf/ActivityCard.html',
      restrict: 'E',
      replace: true,
      scope: {
        activity: '=',
        context: '=',
      },
      link(scope, el) {
        scope.ts = CRM.ts('pelf');
      },
    };
  });

})(angular, CRM.$, CRM._);
