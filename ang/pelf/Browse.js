(function(angular, $, _) {

  angular.module('pelf').config(function($routeProvider) {
      $routeProvider.when('/pelf/browse', {
        controller: 'PelfBrowse',
        templateUrl: '~/pelf/Browse.html',

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
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('pelf').controller('PelfBrowse', function($scope, crmApi, crmStatus, crmUiHelp, $routeParams) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('pelf');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/pelf/Browse'}); // See: templates/CRM/pelf/Browse.hlp

    $scope.state = 'loading';
    $scope.listDetails = 'activity';
    $scope.sortedCases = [];
    $scope.cases = {};
    $scope.filters = {
      sort: 'Status',
      projects: [],
      status: [],
      years: [],
      adjusted: true
    };
    $scope.projectsFilterOptions = {results:[]};
    $scope.yearsFilterOptions = {results:[]};
    $scope.statusFilterOptions = {results:[]};
    $scope.projectPivot = [];
    $scope.projects = [];
    $scope.pageTitle = 'Pelf: All Cases';
    $scope.$watch('filters', applySortAndFilter, true);
    $scope.totals = { adjusted: 0, total: 0 };

    function applySortAndFilter() {
      $scope.sortedCases = [];
      // Recalculate pivots
      const pivots = {};
      if (!$scope.cases) {
        return;
      }
      for (const key in $scope.cases) {
        if (!$scope.cases.hasOwnProperty(key)) {
          continue;
        }
        var item = $scope.cases[key];
        var anyMatch = false;

        // Loop funds.

        // Determine if this case matches our filters.

        // Status match?
        if ($scope.filters.status.length > 0) {
          if ($scope.filters.status.indexOf(item.status_id.toString()) == -1) {
            continue;
          }
        }
        // Project match?
        if ($scope.filters.projects.length > 0) {
          if (!$scope.filters.projects.some(proj => item.projects.indexOf(parseInt(proj))>-1 )) {
            continue;
          }
        }

        // Year match?
        if ($scope.filters.years.length > 0) {
          if (!$scope.filters.years.some((year) => year in item.funds)) {
            continue;
          }
        }

        $scope.sortedCases.push($scope.cases[key]);
      }

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
      else if ($scope.filters.sort == 'Worth Adjusted') {
        $scope.sortedCases.sort((a, b) => {
          return a.funds_total * a.worth_percent / 100 - b.funds_total * b.worth_percent / 100;
        });
      }
    }

    $scope.pelfMoney = function(amount, item) {
      if (!amount) return '';
      amount = adjustAmount(amount, item);
      amount = (Math.round(amount/100) * 100).toString();
      return amount;
    };
    function adjustAmount(amount, item) {
      if (!amount) return '';
      if ($scope.filters.adjusted) {
        amount *= item.worth_percent / 100;
      }
      return Math.round(amount);
    };

    function updateData(r) {
      r = r.values;
      console.log("updateData", r);
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
      $scope.yearsFilterOptions.results = r.financial_years.map(y => ({id: y, text: y}));

      $scope.case_statuses = r.case_statuses;
      $scope.sorted_case_statuses= CRM._.sortBy(CRM._.values(r.case_statuses), s => parseInt(s.weight));
      console.log({unsorted: r.case_statuses, sorted: $scope.sorted_case_statuses});
      $scope.statusFilterOptions.results = Object.keys(r.case_statuses).map(s => ({id: r.case_statuses[s].value, text: r.case_statuses[s].label}));

      $scope.pageTitle = 'Pelf: All Cases';
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
    }
    $scope.reload = reload;

    reload();
  });

})(angular, CRM.$, CRM._);
