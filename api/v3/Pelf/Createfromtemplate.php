<?php
use CRM_Pelf_ExtensionUtil as E;

/**
 * Pelf.Createfromtemplate API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_pelf_Createfromtemplate_spec(&$spec) {
  $spec['templateType']['api.required'] = 1;
  $spec['templateType']['options'] = ['status', 'full'];
  $spec['template']['api.required'] = 1;
}

/**
 * Pelf.Createfromtemplate API
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
function civicrm_api3_pelf_Createfromtemplate($params) {
  if ($params['templateType'] === 'status') {
    $returnValues = pelf()->createStatusTemplate($params['template']);
  }
  else {
    $returnValues = pelf()->createCaseTypeTemplate($params['template']);
  }
  return civicrm_api3_create_success($returnValues, $params, 'Pelf', 'Createfromtemplate');
  // throw new API_Exception(/*error_message*/ 'Everyone knows that the magicword is "sesame"', /*error_code*/ 'magicword_incorrect');
}
