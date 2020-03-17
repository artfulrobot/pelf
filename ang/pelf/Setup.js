(function(angular, $, _) {

  angular.module('pelf').config(function($routeProvider) {
      $routeProvider.when('/pelf/setup', {
        controller: 'PelfSetup',
        templateUrl: '~/pelf/Setup.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve". We don't do this because it's unclear the page is loading.
        resolve: {}
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('pelf').controller('PelfSetup', function($scope, crmApi, crmStatus, crmUiHelp) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('pelf');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/pelf/Setup'}); // See: templates/CRM/pelf/Setup.hlp

    function mustBeObject(obj) {
      if (Array.isArray(obj)) {
        if (obj.length === 0) {
          console.warn("Convert empty array to object:", obj);
          return {};
        }
        else {
          console.warn("Refusing to convert non-empty array to object:", obj);
        }
      }
      return obj;
    }
    function reloaded(r) {
      r = r.values;
      console.log("Pelf config", r);
      if (!r) {
        r = {
        };
      }
      r.pelfConfig.statusMeta = mustBeObject(r.pelfConfig.statusMeta);
      $scope.pelfConfig = r.pelfConfig;
      $scope.caseStatusesInUse = r.caseStatusesInUse;
      $scope.caseTypes = r.caseTypes;
      $scope.isLoading = false;
      $scope.edits = { addCase: ''};
      // Update templates.
      // We need to indicate whether a status exists.
      CRM._.each(templateStatuses, st => {
        st.exists = CRM._.find(r.caseStatusesInUse, candidate => candidate.name == st.name) ? true : false;
      });
      CRM._.each($scope.templates, tpl => {
        tpl.exists = tpl.name in r.caseTypes;
      });
    }
    function handleFail(e) {
      console.log(e);
      alert(e.error_message);
    }

    $scope.handleAddCaseType = function handleAddCase() {
      if (!$scope.edits.addCase) {
        return;
      }
      $scope.pelfConfig.caseTypes.push($scope.edits.addCase);
      $scope.saveConfig('Adding case type', 'Added case type');
    }
    $scope.handleRemoveCaseType = function handleRemoveCaseType(caseTypeName) {
      if (confirm("Removing a case type from pelf could lose a lot of data. Are you sure?")) {
        console.log("old", $scope.pelfConfig, "will remove ", caseTypeName);
        $scope.pelfConfig.caseTypes = CRM._.without($scope.pelfConfig.caseTypes, caseTypeName);
        console.log("new", $scope.pelfConfig);
        $scope.saveConfig('Removing case type', 'Removed case type');
      }
    }
    $scope.saveConfig = function saveConfig(msgDoing, msgDone) {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: msgDoing || ts('Saving...'), success: msgDone || ts('Saved')},
        crmApi('Pelf', 'updateconfig', $scope.pelfConfig)
        .then(reloaded, handleFail)
      );
    };
    $scope.reload = function reload() {
      $scope.isLoading = true;
      return crmApi('Pelf', 'getconfig', {})
      .then(reloaded, handleFail);
    };
    $scope.handleCreateFromTemplate = function handleAddCaseType(caseTpl) {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Creating Case Type...'), success: ts('Created case type')},
        crmApi('Pelf', 'createfromtemplate', { templateType: 'full', template: caseTpl })
        .then($scope.reload, handleFail)
      );
    };
    $scope.handleAddTemplateStatus = function handleAddTemplateStatus(tpl) {


      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Creating case status...'), success: ts('Created case status')},
        crmApi('Pelf', 'createfromtemplate', { templateType: 'status', template: tpl })
        .then($scope.reload, handleFail)
      );
    };

    // Doing code here.
    $scope.isLoading = true;
    $scope.phases = {
      prospect: { grouping: 'Opened', title: ts('Prospect') },
      live    : { grouping:'Opened', title: ts('Live')},
      complete: { grouping:'Closed', title: ts('Completed')},
      failed  : { grouping:'Closed', title: ts('Unsuccessful')},
      dropped : { grouping:'Closed', title: ts('Dropped')}
    };

    const templateStatuses = {
      pelf_hold: {
              name: 'pelf_hold',
              label: 'On hold',
              description: "Work has paused, may resume.",
              color: '#A27256',
              phase: 'dropped',
              weight: 1,
            },
      pelf_identify: {
              name: 'pelf_identify',
              label: 'Identify',
              description: '',
              color: '#DFFBE5',
              phase: 'prospect',
              weight: 5,
            },
      pelf_research: {
              name: 'pelf_research',
              label: 'Research',
              description: "Looking into this possible prospect.",
              color: '#C1EECB',
              phase: 'prospect',
              weight: 10,
            },
      pelf_approach: {
              name: 'pelf_approach',
              label: 'Approach',
              description: "Approaching/contacting/finding way in.",
              color: '#A3E2B0',
              phase: 'prospect',
              weight: 20,
            },
      pelf_concept_development: {
              name: 'pelf_concept_development',
              label: 'Concept development',
              description: "Preparing to submit concept",
              color: '#A3E2B0',
              phase: 'prospect',
              weight: 30,
            },
      pelf_concept_submitted: {
              name: 'pelf_concept_submitted',
              label: 'Concept submitted',
              description: "Concept submitted, waiting to hear",
              color: '#85D596',
              phase: 'prospect',
              weight: 40,
            },
      pelf_proposal_development: {
              name: 'pelf_proposal_development',
              label: 'Proposal development',
              description: "Preparing to submit proposal",
              color: '#A3E2B0',
              phase: 'prospect',
              weight: 42,
            },
      pelf_proposal_submitted: {
              name: 'pelf_proposal_submitted',
              label: 'Proposal submitted',
              description: "Proposal submitted, waiting to hear",
              color: '#85D596',
              phase: 'prospect',
              weight: 44,
            },
      pelf_negotiate: {
              name: 'pelf_negotiate',
              label: 'Negotiating',
              description: "Near completion, discussing terms/contract etc.",
              color: '#85D596',
              phase: 'prospect',
              weight: 50,
            },
      pelf_deal_proposed: {
              name: 'pelf_deal_proposed',
              label: 'Deal Proposed',
              description: "A deal has been proposed.",
              color: '#68C87B',
              phase: 'prospect',
              weight: 60,
            },
      pelf_deal_agreed: {
              name: 'pelf_deal_agreed',
              label: 'Deal Agreed',
              description: "Deal has been agreed, but contract not finalised yet.",
              color: '#4ABB61',
              phase: 'prospect',
              weight: 70,
            },
      pelf_contract: {
              name: 'pelf_contract',
              label: 'Contract',
              description: "Success: Contract agreed",
              color: '#4bbbdd',
              phase: 'live',
              weight: 80,
            },
      pelf_dropped: {
              name: 'pelf_dropped',
              label: 'Dropped',
              description: "We decided not to proceed. e.g. research revealed unsuitable",
              color: '#888888',
              phase: 'dropped',
              weight: 90,
            },
      pelf_failed: {
              name: 'pelf_failed',
              label: 'Failed',
              description: "Bid was declined",
              color: '#997777',
              phase: 'failed',
              weight: 100,
            },
      pelf_completed: {
              name: 'pelf_completed',
              label: 'Completed',
              description: "Contract has been delivered and is now all finished.",
              color: '#7F9977',
              phase: 'complete',
              weight: 110,
            },
    };
    // Add some data to templateStatuses
    CRM._.each(templateStatuses, tpl => {
      // The tpl needs a 'grouping' value, which we can look up from phases.
      tpl.grouping = $scope.phases[tpl.phase].grouping;
    });
    $scope.templates = [
        {
          name: 'pelf_grant',
          title: 'Grant application',
          statuses: [
            templateStatuses.pelf_research,
            templateStatuses.pelf_concept_development,
            templateStatuses.pelf_concept_submitted,
            templateStatuses.pelf_proposal_development,
            templateStatuses.pelf_proposal_submitted,
            templateStatuses.pelf_negotiate,
            templateStatuses.pelf_contract,
            templateStatuses.pelf_dropped,
            templateStatuses.pelf_failed,
            templateStatuses.pelf_completed
          ]
        },
        {
          name: 'pelf_partnership',
          title: 'Partnership',
          statuses: [
            templateStatuses.pelf_hold,
            templateStatuses.pelf_identify,
            templateStatuses.pelf_research,
            templateStatuses.pelf_approach,
            templateStatuses.pelf_negotiate,
            templateStatuses.pelf_deal_proposed,
            templateStatuses.pelf_deal_agreed,
            templateStatuses.pelf_contract,
            templateStatuses.pelf_dropped,
            templateStatuses.pelf_failed,
            templateStatuses.pelf_completed
          ]
        },
    ];
    $scope.reload();

  });

})(angular, CRM.$, CRM._);
