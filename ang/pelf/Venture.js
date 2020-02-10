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
      $scope.dirty = false;
      $scope.projects = {};
      _.each(venture.projects, proj => { $scope.projects[proj.value] = proj; });
      _.each(venture.funds, row => { row.amount = Math.round(parseFloat(row.amount)); })
      $scope.venture = venture;
      $scope.pageTitle = venture.subject;
      $scope.recalculateTotals();
      // Sort funds by financial year, project
      $scope.venture.funds = _.sortByAll($scope.venture.funds, ['fy_start', 'project']);
    }

    // Called when an API call failed.
    function handleFail(r) {
      console.error("handleFail", r);
      alert(r.error_message);
      return false;
    }

    $scope.recalculateTotals = function recalculateTotals(row) {
      if (row) {
        row.changed = true;
        $scope.dirty = true;
      }
    };

    // Initial state is loading.
    $scope.state = 'loading';
    $scope.projectTotals = [];
    $scope.fiscalYearTotals = [];
    $scope.dirty = false;

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
    $scope.saveFunds = function saveFunds() {
      // params.
      const params = {id: $scope.venture.id, funds:[]};
      _.each($scope.venture.funds, row => {
        if (row.changed || !row.id) {
          params.funds.push(row);
        }
      });
      return crmStatus({}, crmApi('Pelf', 'updateventure', params).then(updateData, handleFail));
    };

    //
    // Load data
    //
    $scope.reload();

  });
})(angular, CRM.$, CRM._);
