<?php
use CRM_Pelf_ExtensionUtil as E;

/**
 * Pelf.Updateconfig API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_pelf_Updateconfig_spec(&$spec) {
}

/**
 * Pelf.Updateconfig API
 *
 * Update config and return the output of getApiConfig();
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
function civicrm_api3_pelf_Updateconfig($params) {
  $returnValues = pelf()->updateConfig($params)->getApiConfig();
  return civicrm_api3_create_success($returnValues, $params, 'Pelf', 'Updateconfig');
  // throw new API_Exception(/*error_message*/ 'Everyone knows that the magicword is "sesame"', /*error_code*/ 'magicword_incorrect');
}
