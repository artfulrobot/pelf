<?php
use CRM_Pelf_ExtensionUtil as E;

class CRM_Pelf_BAO_PelfFundsAllocation extends CRM_Pelf_DAO_PelfFundsAllocation {

  /**
   * Create a new PelfFundsAllocation based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Pelf_DAO_PelfFundsAllocation|NULL
   *
  public static function create($params) {
    $className = 'CRM_Pelf_DAO_PelfFundsAllocation';
    $entityName = 'PelfFundsAllocation';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
