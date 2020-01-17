<?php

use Civi\Core\CiviEventInspectorTest;

/**
 *
 */
class CRM_Pelf {
  /** @var CRM_Pelf */
  protected static $instance;

  /**
   * @var int
   */
  public $pelf_venture_case_id;

  /**
   * @var int
   */
  public $case_stage_option_group_id;

  /**
   * Singleton.
   */
  public static function singleton() {
    if (!isset(static::$instance)) {
      static::$instance = new static();
    }
    return static::$instance;
  }

  public function __construct() {
    $_ = (int) civicrm_api3('CaseType', 'getvalue', ['return' => 'id', 'name' => 'pelf_venture']);
    if (!$_) {
      throw new Exception("pelf_venture not found in case types.");
    }
    $this->pelf_venture_case_id = $_;
    $this->case_stage_option_group_id = (int) civicrm_api3('OptionGroup', 'getvalue', ['return' => 'id', 'name'=>'case_status']);
  }

  /**
   * Get data for browsing.
   *
   */
  public function getBrowseData($filters = []) {

    $sql = '';
    $params = [];

    // Fetch cases
    $sql = "SELECT cs.id, cs.subject, v.worth_percent, stage.label stage_label
      FROM civicrm_case cs
      INNER JOIN civicrm_pelf_venture_details v ON cs.id = v.entity_id
      INNER JOIN civicrm_option_value stage ON cs.stage_id = stage.value AND stage.option_group_id = $this->case_stage_option_group_id
      WHERE case_type_id = $this->pelf_venture_case_id";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $cases = [];
    while ($dao->fetch()) {
      $id = (int) $dao->id;
      $cases[$id] = $dao->toArray() + ['funds_total' => 0];
      // Ensure worth_percent is a float.
      $cases[$id]['worth_percent'] = (float) $dao->worth_percent;
    }
    $case_ids_sql = implode(',', array_keys($cases));

    // Fetch clients
    $clients_linked = CRM_Core_DAO::executeQuery(
      "SELECT contact_id, case_id FROM civicrm_case_contact WHERE case_id IN ($case_ids_sql)"
      )->fetchAll();
    $clients = [];
    foreach ($clients_linked as $row) {
      $client_contact_id = (int) $row['contact_id'];
      $case_id = (int) $row['case_id'];
      $clients[$client_contact_id] = NULL;
      $cases[$case_id]['clients'][] = $client_contact_id;
    }
    // Fetch client data
    $result = \Civi\Api4\Contact::get()
      ->setSelect(['id', 'display_name'])
      ->addWhere('id', 'IN', array_keys($clients))
      ->execute();
    foreach ($result as $client) {
      $clients[$client['id']] = $client;
    }

    // Fetch Allocations for these cases.
    $sql = "SELECT * FROM civicrm_pelf_funds_allocation WHERE case_id IN ($case_ids_sql) ORDER BY fy_start";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $financial_years = []; // @Todo
    $unique_projects = [];
    while ($dao->fetch()) {
      $cases[$dao->case_id]['funds'][] = $dao->toArray();
      $cases[$dao->case_id]['funds_total'] += $dao->amount;
      $unique_projects[(int) $dao->project] = NULL;
    }

    // Fetch all projects.
    $projects = Civi\Api4\OptionValue::get()
      ->setSelect(['value', 'label'])
      ->addWhere('option_group.name', '=', 'pelf_project')
      ->execute()
      ->indexBy('value');

    return [
      'clients' => $clients,
      'cases'   => $cases,
      'projects' => $projects,
    ];
  }
}
