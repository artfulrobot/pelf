<?php
use CRM_Pelf_ExtensionUtil as E;

/**
 * Pelf.Updateventure API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_pelf_Updateventure_spec(&$spec) {
  $spec['id']['api.required'] = 1;
  $spec['id']['description'] = 'Case ID';
}

/**
 * Pelf.Updateventure API
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
function civicrm_api3_pelf_Updateventure($params) {
  // validate id
  $pelf = pelf();
  $pelf->validateCaseId($params['id']);
  $projects = $pelf->getProjects();
  foreach ($params['funds'] as $row) {
    $bao = new CRM_Pelf_BAO_PelfFundsAllocation();
    $rowShouldNotExist = empty($row['amount']);

    if ($row['id']) {
      $bao->id = $row['id'];
      if (!$bao->find(1)) {
        // Row not found, but it was there when we started editing.
        throw new API_Exception("Tried to update a row that does not exist.");
      }
      // Row found. If the incoming amount is empty, delete this row.
      if ($rowShouldNotExist) {
        // Delete this row.
        $bao->delete();
      }
    }
    if ($rowShouldNotExist) {
      // Row is to be ignored as the amount is blank.
      continue;
    }
    else {
      // Row should exist. Continue to check validity.
      if (!isset($projects[$row['project']])) {
        throw new API_Exception("Invalid project");
      }
      if (!preg_match('/^\d\d\d\d-\d\d-\d\d$/', $row['fy_start'])) {
        throw new API_Exception("Invalid fiscal year start date.");
      }
      $bao->case_id = $params['id'];
      $bao->project = $row['project'];
      $bao->amount = $row['amount'];
      $bao->fy_start = $row['fy_start'];
      $bao->save();
    }
  }
  return civicrm_api3('Pelf', 'getventure', ['id' => $params['id']]);
}
