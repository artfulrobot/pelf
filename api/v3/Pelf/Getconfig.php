<?php
use CRM_Pelf_ExtensionUtil as E;

/**
 * Pelf.Getconfig API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_pelf_Getconfig_spec(&$spec) {
}

/**
 * Pelf.Getconfig API
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
function civicrm_api3_pelf_Getconfig($params) {
  return civicrm_api3_create_success(pelf()->getApiConfig(), $params, 'Pelf', 'Getconfig');
}
