<?php

use Civi\Core\CiviEventInspectorTest;

/**
 *
 */
class CRM_Pelf {
  /** @var CRM_Pelf */
  protected static $instance;

  /**
   * @var array of case types that we are configured to work with. Populated in getConfig()
   */
  protected $caseTypes;

  /**
   * @var int
   */
  public $case_stage_option_group_id;

  public $activity_type_option_group_id;
  /** I believe this is always a constant */
  public $activity_record_type_assignee = 1;
  /**
   * @var API name for pelf_worth_percent
   */
  public $worthPercentApiName;
  /** @var array from decoded pelf_config setting */
  protected $config;
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
    $this->case_stage_option_group_id = (int) civicrm_api3('OptionGroup', 'getvalue', ['return' => 'id', 'name'=>'case_status']);
    $this->activity_type_option_group_id = (int) civicrm_api3('OptionGroup', 'getvalue', ['return' => 'id', 'name'=>'activity_type']);
    $customFieldID = CRM_Core_BAO_CustomField::getCustomFieldID('pelf_worth_percent', 'pelf_venture_details');
    $this->worthPercentApiName = 'custom_' . $customFieldID;

    $this->getConfig();
  }
  /**
   * Accessor for caseTypes.
   *
   * @return array
   */
  public function getCaseTypes() {
    return $this->caseTypes;
  }
  /**
   * Access config array.
   *
   * @param bool $reload
   * @return array
   */
  public function getConfig($reload = FALSE) {
    if ($reload || !$this->config) {
      $this->config = json_decode(Civi::settings()->get('pelf_config'), TRUE);
      if (!$this->config) {
        $this->config = [
          'caseTypes' => [],
          'statusMeta' => [],
        ];
      }

      // Make a lookup of case type name to id.
      $this->caseTypes = [];
      if ($this->config['caseTypes']) {
        foreach (civicrm_api3('CaseType', 'get',
          [
            'return'  => ['id', 'name', 'title'],
            'options' => ['limit' => 0],
            'name'    => ['IN' => $this->config['caseTypes']]])['values'] ?? [] as $_) {
            $this->caseTypes[(int) $_['id']] = $_;
        }
      }
    }
    return $this->config;
  }

  /**
   * Handles Pelf.getconfig API requests.
   *
   * @return array
   */
  public function getApiConfig() {
    // Look up case statuses in use.

    // proof of concept: return all case stati @todo
    $results = \Civi\Api4\OptionValue::get()
      ->addWhere('option_group.name', '=', 'case_status')
      ->addOrderBy('weight', 'ASC')
      ->addOrderBy('label', 'ASC')
      ->setCheckPermissions(FALSE)
      ->execute();

    // Fetch all case types.
    $caseTypes = [];
    foreach (civicrm_api3('CaseType', 'get', ['options' => ['limit' => 0], 'return' => ['name', 'title']])['values'] ?? [] as $row) {
      $caseTypes[$row['name']] = $row;
    }

    $return = [
      'caseTypes'         => $caseTypes,
      'pelfConfig'        => $this->getConfig(),
      'caseStatusesInUse' => $results,
    ];

    return $return;
  }
  /**
   * Updates the config.
   *
   * @param array
   */
  public function updateConfig($newConfig) {
    $oldConfig = $this->getConfig();
    if (!isset($newConfig['caseTypes'])) {
      throw new InvalidArgumentException("Missing caseTypes key");
    }

    // New case types.
    $additions = array_diff($newConfig['caseTypes'], $oldConfig['caseTypes']);
    $removals = array_diff($oldConfig['caseTypes'], $newConfig['caseTypes']);
    if ($additions || $removals) {

      $case_type_ids = array_column(
        civicrm_api3('CaseType', 'get', ['return' => ['id'], 'name' => ['IN' => $newConfig['caseTypes'], 'options' => ['limit' => 0]]])['values'] ?? [],
        'id'
      );

      $results = \Civi\Api4\CustomGroup::update()
        ->addWhere('name', '=', 'pelf_venture_details')
        ->addValue('extends_entity_column_value', $case_type_ids)
        ->execute();
    }

    // Now save setting.
    Civi::settings()->set('pelf_config', json_encode($newConfig));
    $this->getConfig(TRUE);

    return $this;
  }
  /**
   * Get data for browsing.
   *
   */
  public function getBrowseData($filters = []) {

    $sql = '';
    $params = [];

    // Limit by a particular case type?
    $allowed_case_types = array_keys($this->caseTypes);
    if ($filters['case_type_id']) {
      $case_type_id = (int) $filters['case_type_id'];
      if ($case_type_id > 0) {
        $allowed_case_types = [$case_type_id];
      }
    }

    // Fetch cases
    $allowed_case_types = implode(',', $allowed_case_types);
    $sql = "SELECT cs.id, ct.name, cs.subject, v.worth_percent, status_id, stage.label status_label, cs.case_type_id
      FROM civicrm_case cs
      INNER JOIN civicrm_case_type ct ON cs.case_type_id = ct.id
      INNER JOIN civicrm_pelf_venture_details v ON cs.id = v.entity_id
      INNER JOIN civicrm_option_value stage ON cs.status_id = stage.value AND stage.option_group_id = $this->case_stage_option_group_id
      WHERE case_type_id IN ($allowed_case_types) AND cs.is_deleted = 0";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $cases = [];
    $used_case_statuses = [];
    while ($dao->fetch()) {
      $id = (int) $dao->id;
      $cases[$id] = $dao->toArray() + ['funds_total' => 0];
      // Ensure worth_percent is a float.
      $cases[$id]['worth_percent'] = (float) $dao->worth_percent;
      // $cases[$id]['projects'] = [];
      $used_case_statuses[$dao->status_id] = TRUE;
    }

    $case_ids_sql = implode(',', array_keys($cases));

    // Fetch all projects.
    $projects = Civi\Api4\OptionValue::get()
      ->setSelect(['value', 'label', 'color', 'grouping'])
      ->addWhere('option_group.name', '=', 'pelf_project')
      ->addWhere('is_active', '=', 1)
      ->addOrderBy('label')
      ->execute()
      ->indexBy('value');

    $summary_pivot_project_empty = [];
    foreach ($projects as $project) {
      preg_match('/^(.*?)\s*:\s*(.*)$/', $project['label'], $_);
      $project_group = $_[1] ?? '(Ungrouped)';
      $project_name = $_[2] ?? $dao->project;
      $summary_pivot_project_empty[$project_group][$project_name] = [];
    }

    if ($cases) {

      // Fetch all clients for these cases
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

      // Fetch last completed activity and first scheduled one.
      $this->addNearActivities($cases);

      // Fetch Allocations for these cases.
      // Helpful to know which projects are encountered for each case.
      $sql = "SELECT * FROM civicrm_pelf_funds_allocation WHERE case_id IN ($case_ids_sql) ORDER BY fy_start";
      $dao = CRM_Core_DAO::executeQuery($sql);
      $financial_years = [];
      $projects_by_case = [];
      $summary_pivot_project = $summary_pivot_project_empty;
      $summary_pivot_status = [];
      $totals = ['total' => 0, 'adjusted' => 0];

      // Turn flat list into [project_group][project_name][fy][proj][adjusted|total] = total amount
      while ($dao->fetch()) {
        $_ = $dao->toArray();
        $case = $cases[$dao->case_id];

        // Keep track of financial years encountered and simplify
        $year = substr($dao->fy_start, 0, 4);
        if (substr($dao->fy_start, 6,5) !== '01-01') {
          // Unless year start is 1 Jan, show FY as 2020-2021
          $year .= '-' . ($year + 1);
        }
        $financial_years[$year] = NULL;
        $_['fy'] = $year;
        $cases[$dao->case_id]['funds'][$year][$dao->project] = $dao->amount;

        // Track projects
        $projects_by_case[(int) $dao->case_id][(int) $dao->project] = NULL;

        // Grand totals
        $totals['total'] += $dao->amount;
        $totals['adjusted'] += $dao->amount * $case['worth_percent']/100;

        // Project pivot calcs.
        preg_match('/^(.*?)\s*:\s*(.*)$/', $projects[$dao->project]['label'], $_);
        $project_group = $_[1] ?? '(Ungrouped)';
        $project_name = $_[2] ?? $dao->project;
        if (!isset($summary_pivot_project[$project_group][$project_name][$year])) {
          $summary_pivot_project[$project_group][$project_name][$year] = ['total' => 0, 'adjusted' => 0];
        }
        $summary_pivot_project[$project_group][$project_name][$year]['total'] += $dao->amount;
        $summary_pivot_project[$project_group][$project_name][$year]['adjusted'] += $dao->amount * $case['worth_percent']/100;

        // Status pivot calcs.
        if (!isset($summary_pivot_status[$case['status_id']])) {
          $summary_pivot_status[$case['status_id']] = [];
        }
        $summary_pivot_status[$case['status_id']][$year]['total'] += $dao->amount;
        $summary_pivot_status[$case['status_id']][$year]['adjusted'] += $dao->amount * $case['worth_percent']/100;

        $cases[$dao->case_id]['funds_total'] += $dao->amount;
        //$unique_projects[(int) $dao->project] = NULL;
      }
      // Merge in projects_by_case
      foreach ($projects_by_case as $case_id => $case_projects) {
        $case_projects = array_keys($case_projects);
        sort($case_projects);
        $cases[$case_id]['projects'] = $case_projects;
      }
    }

    // Project pivot
    // We want totals by project group
    $new = [];
    foreach ($summary_pivot_project as $project_group => $by_project) {
      $subtots = [];
      foreach ($by_project as $project => $by_year) {
        foreach ($by_year as $year => $_) {
          if (!isset($subtots[$year])) {
            $subtots[$year] = ['total' => 0, 'adjusted' => 0];
          }
          $subtots[$year]['total'] += $_['total'];
          $subtots[$year]['adjusted'] += $_['adjusted'];
        }
      }
      $summary_pivot_project[$project_group]['Subtotal'] = $subtots;
      // Flatten the project group back in to the project.
      foreach ($summary_pivot_project[$project_group] as $project_name => $_) {
        $new["$project_group: $project_name"] = $_;
      }
    }
    $summary_pivot_project = $new;
    // PHP remembers the order of array keys, but whn converted to JSON and then to a js object the order is not preserved.
    // Therefore we now create an array to preserve the order.
    $summary_pivot_project = $this->hashToArray($summary_pivot_project, 1);

    // Fetch all case statuses. @todo is active
    $case_statuses = Civi\Api4\OptionValue::get()
      ->setSelect(['value', 'label', 'color', 'grouping', 'weight'])
      ->addWhere('option_group.name', '=', 'case_status')
      ->addWhere('value', 'IN', array_keys($used_case_statuses))
      ->addWhere('is_active', '=', '1')
      ->execute()
      ->indexBy('value');

    return [
      'clients'         => $clients,
      'caseTypes'       => $this->caseTypes,
      'cases'           => $cases,
      'case_statuses'   => $case_statuses,
      'projects'        => $projects,
      'financial_years' => array_keys($financial_years),
      'totals'          => $totals,
      'pivot_projects'  => $summary_pivot_project,
      'pivot_status'    => $summary_pivot_status,
    ];
  }
  protected function addNearActivities(&$cases) {
    // First find the last completed activity for each case.

    // Do this with SQL to get most of the data, then filter it by running the
    // activities through API4 to apply permissions.
    implode(',', array_map(function ($_) { return (int) $_; }, array_column($cases, 'id')));

    $case_ids = [];
    foreach ($cases as &$case) {
      $case += ['activityLast' => NULL, 'activityNext' => NULL];
      $case_ids[] = (int) $case['id'];
    }
    if (!$case_ids) {
      return;
    }
    $case_ids = implode(',', $case_ids);
    $sql = "SELECT
      a.id, a.subject, a.activity_date_time,
      atype.label activity_type,
      ca.case_id,
      (
        SELECT GROUP_CONCAT(c.display_name SEPARATOR ', ')
        FROM civicrm_activity_contact ac
          INNER JOIN civicrm_contact c ON c.id = ac.contact_id
        WHERE ac.activity_id = a.id AND ac.record_type_id = $this->activity_record_type_assignee
      ) assigees
      FROM civicrm_activity a
      INNER JOIN civicrm_case_activity ca
        ON ca.activity_id = a.id AND ca.case_id IN ($case_ids)
      INNER JOIN civicrm_option_value atype
        ON atype.value = a.activity_type_id AND atype.option_group_id = $this->activity_type_option_group_id
      WHERE
        a.activity_date_time < NOW()
        AND NOT EXISTS (
          SELECT 1
          FROM civicrm_case_activity ca2
          INNER JOIN civicrm_activity a2 ON ca2.activity_id = a2.id AND ca2.case_id = ca2.case_id
          WHERE a2.activity_date_time > a.activity_date_time AND a2.activity_date_time < NOW()
        )
    ";
    $results = CRM_Core_DAO::executeQuery($sql);
    // @todo check permissions
    while ($results->fetch()) {
      $cases[$results->case_id]['activityLast'] = $results->toArray();
      $cases[$results->case_id]['activityLast']['activity_date_time'] =
        date('j M Y', strtotime($cases[$results->case_id]['activityLast']['activity_date_time']));
    }

    $sql = "SELECT
      a.id, a.subject, a.activity_date_time,
      atype.label,
      ca.case_id,
      (
        SELECT GROUP_CONCAT(c.display_name SEPARATOR ', ')
        FROM civicrm_activity_contact ac
          INNER JOIN civicrm_contact c ON c.id = ac.contact_id
        WHERE ac.activity_id = a.id AND ac.record_type_id = $this->activity_record_type_assignee
      ) assigees
      FROM civicrm_activity a
      INNER JOIN civicrm_case_activity ca
        ON ca.activity_id = a.id AND ca.case_id IN ($case_ids)
      INNER JOIN civicrm_option_value atype
        ON atype.value = a.activity_type_id AND atype.option_group_id = $this->activity_type_option_group_id
      WHERE
        a.activity_date_time > NOW()
        AND NOT EXISTS (
          SELECT 1
          FROM civicrm_case_activity ca2
          INNER JOIN civicrm_activity a2 ON ca2.activity_id = a2.id AND ca2.case_id = ca2.case_id
          WHERE a2.activity_date_time < a.activity_date_time AND a2.activity_date_time > NOW()
        )
    ";
    $results = CRM_Core_DAO::executeQuery($sql);
    // @todo check permissions
    while ($results->fetch()) {
      $cases[$results->case_id]['activityNext'] = $results->toArray();
    }
  }
  /**
   * Create the case status.
   *
   * @param array $tpl
   */
  public function createStatusTemplate($tpl) {

    // Check if it exists.
    if (Civi\Api4\OptionValue::get()
      ->setCheckPermissions(FALSE)
      ->selectRowCount()
      ->addWhere('option_group_id', '=', $this->case_stage_option_group_id)
      ->addWhere('name', '=', $tpl['name'])
      ->execute()
      ->count() > 0) {
      throw new InvalidArgumentException("This status is already defined. It may have been renamed/set inactive/removed from your case type, but as it exists we cannot create it from the template.");
    }
    // Create it.
    \Civi\Api4\OptionValue::create()
      ->addValue('option_group_id', $this->case_stage_option_group_id)
      ->addValue('label', $tpl['label'])
      ->addValue('name', $tpl['name'])
      ->addValue('description', $tpl['description'])
      ->addValue('color', $tpl['color'])
      ->addValue('is_active', 1)
      ->addValue('grouping', $tpl['grouping'])
      ->addValue('weight', $tpl['weight'])
      ->execute();

    // Save the associated phase.
    $config = $this->getConfig();
    $config['statusMeta'][$tpl['name']] = ['phase' => $tpl['phase']];
    $this->updateConfig($config);

  }
  /**
   * Create the case type and all statuses.
   *
   * @param array $tpl
   */
  public function createCaseTypeTemplate($tpl) {

    // Check if it exists.
    $case_type_id = civicrm_api3('CaseType', 'get', [ 'return' => 'id', 'name' => $tpl['name'] ])['id'] ?? NULL;
    if (!$case_type_id) {
      // Create it.
      if (empty($tpl['title'])) {
        throw new \InvalidArgumentException(__FUNCTION__ . " input is missing 'title' parameter.");
      }
      $params = [
        'name'  => $tpl['name'],
        'title' => $tpl['title'],
        "is_active" => "1",
        "definition" => [
          "activityTypes" => [
            [ "name" => "Open Case", "max_instances" => "1" ],
            [ "name" => "Email" ],
            [ "name" => "Follow up" ],
            [ "name" => "Meeting" ],
            [ "name" => "Phone Call" ],
          ],
          "activitySets" => [
            [
              "name" => "standard_timeline",
              "label" => "Standard Timeline",
              "timeline" => "1",
              "activityTypes" => [
                [
                  "name" => "Open Case",
                  "status" => "Completed",
                  "label" => "Open Case",
                  "default_assignee_type" => "1"
                ]
              ]
            ]
          ],
          "timelineActivityTypes" => [
            [
              "name" => "Open Case",
              "status" => "Completed",
              "label" => "Open Case",
              "default_assignee_type" => "1"
            ]
          ],
          "caseRoles" => [
            [
              "name" => "Case Coordinator",
              "creator" => "1",
              "manager" => "1"
            ]
          ],
        ]
      ];
      $case_type_id = civicrm_api3('CaseType', 'create', $params)['id'];
    }

    // Add it to our case types.
    $config = $this->getConfig();
    if (!in_array($config['caseTypes'], $tpl['name'])) {
      $config['caseTypes'][] = $tpl['name'];
      $this->updateConfig($config);
    }

    // Create the statuses for it.
    foreach ($tpl['statuses'] as $statusTpl) {
      try {
        $this->createStatusTemplate($statusTpl);
      }
      catch (InvalidArgumentException $e) {
        if (substr($e->getMessage(), 0, 31) === 'This status is already defined.') {
          continue;
        }
      }
    }

    // @todo how do we associate the statuses with the case type?

  }
  /**
   *
   * Called with max_level = 2 and the following data:
   * [ '2020' => [
   *      'Jan' => <whatever1>,
   *      'Feb' => <whatever2>,
   *      ],
   *   '2021' => [
   *      'Jan' => <whatever3>,
   *      ]
   * ]
   *
   * we should return:
   * [
   *   [ 'key' => '2020',
   *     'data' => [
   *        [ 'key' => 'Jan', 'data' => <whatever1> ],
   *        [ 'key' => 'Feb', 'data' => <whatever2> ],
   *     ],
   *   ],
   *   [ 'key' => '2021',
   *     'data' => [
   *        [ 'key' => 'Jan', 'data' => <whatever3> ],
   *     ],
   *   ],
   * ]
   *
   */
  public function hashToArray($hash, $max_level) {
    $sorted = [];
    foreach ($hash as $k => $v) {
      if (is_array($v) && $max_level > 1) {
        $v = $this->hashToArray($v, $max_level - 1);
      }
      $sorted[] = ['key' => $k, 'data' => $v];
    }
    return $sorted;
  }
  /**
   * Get fiscal years options.
   */
  public function getFiscalYearsOptions($mustInclude = []) {
    $start = Civi::settings()->get('fiscalYearStart');
    $start_month = str_pad($start['M'], 2, '0', STR_PAD_LEFT);
    $start_day   = str_pad($start['d'], 2, '0', STR_PAD_LEFT);
    $suffix = "-$start_month-$start_day";

    // Get years from must includes.
    $years = array_map(function($date) { return substr($date, 0, 4); }, $mustInclude);
    sort($years);
    $earliest = min(date('Y') - 10, reset($years));
    $latest = max(date('Y') + 10, end($years));

    $options = [];
    for ($year = $earliest; $year <= $latest; $year++) {
      $options["$year$suffix"] = ($suffix === '-01-01') ? $year : "$year - " . ($year+1);
    }

    // Add in any $mustInclude that had a different fiscal year start.
    foreach ($mustInclude as $fy_start) {
      if (!isset($options[$fy_start])) {
        $options[$fy_start] = date('j M Y', strtotime($fy_start));
      }
    }

    return $options;
  }
  /**
   * Throw an exception if the case does not exist, or if it's not a Pelf case type.
   */
  public function validateCaseId($id) {
    civicrm_api3('Case', 'getsingle', ['id' => $id, 'case_type_id' => ['IN' => array_keys($this->caseTypes)]]);
  }
  /**
   * Get projects, indexed by the option value.
   *
   * @return array
   */
  public function getProjects() {
    return Civi\Api4\OptionValue::get()
      ->setSelect(['value', 'label', 'color', 'grouping'])
      ->addWhere('option_group.name', '=', 'pelf_project')
      ->addWhere('is_active', '=', 1)
      ->addOrderBy('label')
      ->execute()
      ->indexBy('value')
      ->getArrayCopy();
  }
}
