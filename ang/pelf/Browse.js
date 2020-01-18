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
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('pelf').controller('PelfBrowse', function($scope, crmApi, crmStatus, crmUiHelp) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('pelf');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/pelf/Browse'}); // See: templates/CRM/pelf/Browse.hlp

    $scope.state = 'loading';
    $scope.sortedCases = [];
    $scope.cases = {};
    $scope.filters = {
      sort: 'Status',
      projects: []
    };
    $scope.projectsFilterOptions = {results:[]};
    $scope.$watch('filters', applySortAndFilter, true);

    function applySortAndFilter() {
      $scope.sortedCases = [];
      if (!$scope.cases) {
        return;
      }
      for (const key in $scope.cases) {
        if (!$scope.cases.hasOwnProperty(key)) {
          continue;
        }
        var item = $scope.cases[key];

        // Determine if this case matches our filters.

        // Project match?
        if ($scope.filters.projects.length > 0) {
          var anyMatch = false;
          var selectedProjects = $scope.filters.projects;
          for (var i=item.projects.length-1;i>-1;i--) {
            console.log("checking ", item.projects[i].toString(), 'for match in ',  selectedProjects);
            if (selectedProjects.indexOf(item.projects[i].toString()) > -1) {
              anyMatch = true;
              break;
            }
          }
          if (!anyMatch) {
            continue;
          }
        }

        $scope.sortedCases.push($scope.cases[key]);
      }

      if ($scope.filters.sort == 'Status') {
        $scope.sortedCases.sort((a, b) => {
          return b.status_id - a.status_id;
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

    function updateData(r) {
      r = r.values;
      console.log("updateData", r);
      $scope.cases = r.cases;
      $scope.clients = r.clients;
      $scope.state = 'loaded';
      $scope.financial_years = r.financial_years;
      $scope.projects = r.projects;
      $scope.projectsFilterOptions.results = [];
      for (const key in r.projects) {
        if (r.projects.hasOwnProperty(key)) {
          $scope.projectsFilterOptions.results.push({id: (r.projects[key].value), text: r.projects[key].label });
        }
      }
      $scope.case_statuses = r.case_statuses;

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
      crmApi('Pelf', 'getbrowse', params).then(updateData, handleFail);
    }
    $scope.reload = reload;

    reload();
  });

})(angular, CRM.$, CRM._);
