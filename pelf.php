<?php

require_once 'pelf.civix.php';
use CRM_Pelf_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/ 
 */
function pelf_civicrm_config(&$config) {
  _pelf_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function pelf_civicrm_xmlMenu(&$files) {
  _pelf_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function pelf_civicrm_install() {
  _pelf_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function pelf_civicrm_postInstall() {
  _pelf_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function pelf_civicrm_uninstall() {
  _pelf_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function pelf_civicrm_enable() {
  _pelf_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function pelf_civicrm_disable() {
  _pelf_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function pelf_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _pelf_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function pelf_civicrm_managed(&$entities) {
  _pelf_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function pelf_civicrm_caseTypes(&$caseTypes) {
  _pelf_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function pelf_civicrm_angularModules(&$angularModules) {
  _pelf_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function pelf_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _pelf_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function pelf_civicrm_entityTypes(&$entityTypes) {
  _pelf_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_thems().
 */
function pelf_civicrm_themes(&$themes) {
  _pelf_civix_civicrm_themes($themes);
}

/**
 * Implemented hook_civicrm_permission
 *
 */
function pelf_civicrm_permission(&$permissions) {
  $permissions['administer pelf'] = ts('Administer Pelf');
}

/**
 * Implements hook_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions)
 */
function pelf_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
  $permissions['pelf']['createfromtemplate'] = ['administer pelf'];
  $permissions['pelf']['getbrowse'] = ['access all cases and activities'];
  $permissions['pelf']['getconfig'] = ['access all cases and activities'];
  $permissions['pelf']['getventure'] = ['access all cases and activities'];
  $permissions['pelf']['updateconfig'] = ['administer pelf'];
  $permissions['pelf']['updateventure'] = ['access all cases and activities'];
  // @todo PelfFundsAllocation actions.
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function pelf_civicrm_navigationMenu(&$menu) {
  _pelf_civix_insert_navigation_menu($menu, 'Administer/CiviCase', array(
    'label' => E::ts('Setup Pelf'),
    'name' => 'pelf_config',
    'url' => 'civicrm/a#pelf/setup',
    'permission' => 'administer pelf',
    'operator' => 'OR',
    'separator' => 0,
  ));

  _pelf_civix_insert_navigation_menu($menu, 'Cases', array(
    'label'      => 'Pelf: summary',
    'name'       => 'pelf_browse_type_all',
    'url'        => 'civicrm/a#pelf/browse/',
    'permission' => 'access my cases and activities,access all cases and activities',
    'operator'   => 'OR',
    'separator'  => 0,
  ));
  foreach (pelf()->getCaseTypes() as $id => $_) {
    _pelf_civix_insert_navigation_menu($menu, 'Cases', array(
      'label'      => 'Pelf: ' . $_['title'],
      'name'       => 'pelf_browse_type_' . $id,
      'url'        => 'civicrm/a#pelf/browse/type/' . $id,
      'permission' => 'access my cases and activities,access all cases and activities',
      'operator'   => 'OR',
      'separator'  => 0,
    ));
  }
  _pelf_civix_navigationMenu($menu);
}


/**
 * Syntatic sugar.
 *
 * @return CRM_Pelf
 */
function pelf() {
  return CRM_Pelf::singleton();
}
