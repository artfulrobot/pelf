(function(angular, $, _) {
  angular.module('pelf').config(function($routeProvider) {
      $routeProvider.when('/pelf/venture/:case_id', {
        controller: 'PelfVenture',
        templateUrl: '~/pelf/Venture.html',
      });
    }
  );

  angular.module('pelf').controller('PelfVenture', function($scope, crmApi, crmStatus, crmUiHelp, $routeParams) {

    //
    // Define functions and scope objects
    //

    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('pelf');

    // Called when data has been loaded.
    function updateData(r) {
      const venture = r.values;
      $scope.state = 'loaded';
      $scope.projects = {};
      _.each(venture.projects, proj => { $scope.projects[proj.id] = proj; });
      $scope.venture = venture;
      $scope.pageTitle = venture.subject;
    }

    // Called when an API call failed.
    function handleFail(r) {
      console.error("handleFail", r);
      alert(r.error_message);
      return;
    }

    $scope.recalculateTotals = function recalculateTotals() {
      $scope.total = 0;
      _.each($scope.venture.funds, row => { $scope.total += row.amount; });
    };

    // Initial state is loading.
    $scope.state = 'loading';

    // Load data via API call.
    $scope.reload = function reload() {
      if (!$routeParams.case_id) {
        $scope.error = "Invalid URL: missing case ID.";
        $scope.state = "failed";
        return;
      }

      $scope.state = 'loading';
      params = { id: $routeParams.case_id };
      crmApi('Pelf', 'getventure', params).then(updateData, handleFail);
    }

    //
    // Load data
    //
    $scope.reload();

  });
})(angular, CRM.$, CRM._);
