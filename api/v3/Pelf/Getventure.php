<?php
use CRM_Pelf_ExtensionUtil as E;

/**
 * Pelf.Getventure API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_pelf_Getventure_spec(&$spec) {
  $spec['id']['api.required'] = 1;
  $spec['id']['description'] = 'Case Id';
}

/**
 * Pelf.Getventure API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_pelf_Getventure($params) {

  $returnValues = civicrm_api3('Case', 'getsingle', ['id' => $params['id'], 'is_deleted' => 0]);

  $pelf = pelf();
  // Load worth percent
  $_ = $pelf->worthPercentApiName;
  $v = ['entityID' => $params['id']];
  $returnValues['worth_percent'] = CRM_Core_BAO_CustomValueTable::getValues($v)[$_] ?? NULL;

  // Now load all the funds records for this case
  $bao = new CRM_Pelf_BAO_PelfFundsAllocation();
  $bao->case_id = $params['id'];
  $bao->find();
  $returnValues['funds'] = $bao->fetchAll();
  $fiscalYears = [];
  foreach ($returnValues['funds'] as $_) {
    $fiscalYears[$_['fy_start']] = TRUE;
  }
  $returnValues['fiscalYears'] = $pelf->getFiscalYearsOptions(array_keys($fiscalYears));

  // We'll need all the projects.
  $returnValues['projects'] =  pelf()->getProjects();
  return civicrm_api3_create_success($returnValues, $params, 'Pelf', 'Getventure');
  // throw new API_Exception(/*error_message*/ 'Everyone knows that the magicword is "sesame"', /*error_code*/ 'magicword_incorrect');
}
