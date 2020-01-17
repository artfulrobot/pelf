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
    $scope.filters = {
    };

    function updateData(r) {
      console.log("updateData", r);
      r = r.values;
      $scope.cases = r.cases;
      $scope.clients = r.clients;
      $scope.state = 'loaded';
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

    $scope.save = function save() {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Saving...'), success: ts('Saved')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Contact', 'create', {
          id: myContact.id,
          first_name: myContact.first_name,
          last_name: myContact.last_name
        })
      );
    };
  });

})(angular, CRM.$, CRM._);
