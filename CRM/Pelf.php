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
    $sql = "SELECT cs.id, cs.subject, v.worth_percent, status_id, stage.label status_label
      FROM civicrm_case cs
      INNER JOIN civicrm_pelf_venture_details v ON cs.id = v.entity_id
      INNER JOIN civicrm_option_value stage ON cs.status_id = stage.value AND stage.option_group_id = $this->case_stage_option_group_id
      WHERE case_type_id = $this->pelf_venture_case_id";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $cases = [];
    while ($dao->fetch()) {
      $id = (int) $dao->id;
      $cases[$id] = $dao->toArray() + ['funds_total' => 0];
      // Ensure worth_percent is a float.
      $cases[$id]['worth_percent'] = (float) $dao->worth_percent;
      // $cases[$id]['projects'] = [];
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
    // Helpful to know which projects are encountered for each case.
    $sql = "SELECT * FROM civicrm_pelf_funds_allocation WHERE case_id IN ($case_ids_sql) ORDER BY fy_start";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $financial_years = [];
    $projects_by_case = [];
    $summary_pivot_project = [];
    $summary_pivot_status = [];

    // Turn flat list into [fy][proj] = total pivot
    while ($dao->fetch()) {
      $_ = $dao->toArray();
      $case = $cases[$dao->case_id];

      // Track projects
      $projects_by_case[(int) $dao->case_id][(int) $dao->project] = NULL;

      // Project pivot calcs.
      if (!isset($summary_pivot_project[$dao->project])) {
        $summary_pivot_project[$dao->project] = ['total' => 0, 'adjusted' => 0];
      }
      $summary_pivot_project[$dao->project]['total'] += $dao->amount;
      $summary_pivot_project[$dao->project]['adjusted'] += $dao->amount * $case['worth_percent']/100;

      // Status pivot calcs.
      if (!isset($summary_pivot_status[$case['status_id']])) {
        $summary_pivot_status[$case['status_id']] = ['total' => 0, 'adjusted' => 0];
      }
      $summary_pivot_status[$case['status_id']]['total'] += $dao->amount;
      $summary_pivot_status[$case['status_id']]['adjusted'] += $dao->amount * $case['worth_percent']/100;

      // Keep track of financial years encountered and simplify
      $year = substr($dao->fy_start, 0, 4);
      if (substr($dao->fy_start, 6,5) !== '01-01') {
        // Unless year start is 1 Jan, show FY as 2020-2021
        $year .= '-' . ($year + 1);
      }
      $financial_years[$year] = NULL;
      $_['fy'] = $year;

      $cases[$dao->case_id]['funds'][$year][$dao->project] = $dao->amount;
      $cases[$dao->case_id]['funds_total'] += $dao->amount;
      //$unique_projects[(int) $dao->project] = NULL;
    }
    // Merge in projects_by_case
    foreach ($projects_by_case as $case_id => $projects) {
      $projects = array_keys($projects);
      sort($projects); // @todo by alphabet
      $cases[$case_id]['projects'] = $projects;
    }

    // Fetch all projects. @todo is active
    $projects = Civi\Api4\OptionValue::get()
      ->setSelect(['value', 'label', 'color', 'grouping'])
      ->addWhere('option_group.name', '=', 'pelf_project')
      ->execute()
      ->indexBy('value');

    // Fetch all case statuses. @todo is active
    $case_statuses = Civi\Api4\OptionValue::get()
      ->setSelect(['value', 'label', 'color', 'grouping'])
      ->addWhere('option_group.name', '=', 'case_status')
      ->execute()
      ->indexBy('value');

    return [
      'clients'         => $clients,
      'cases'           => $cases,
      'case_statuses'   => $case_statuses,
      'projects'        => $projects,
      'financial_years' => array_keys($financial_years),
      'pivot_projects'  => $summary_pivot_project,
      'pivot_status'    => $summary_pivot_status,
    ];
  }
}
