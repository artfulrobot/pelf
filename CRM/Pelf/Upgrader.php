<?php
use CRM_Pelf_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Pelf_Upgrader extends CRM_Pelf_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   */
  public function install() { }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
  public function postInstall() {
    // Create case type.
    $baseParams = ['name' => 'pelf_venture'];
    $allParams = [
      "title" => "Venture",
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
    $case_id = $this->createOrUpdate('CaseType', $baseParams, $allParams);

    // We need custom fieldset.
    $baseParams = [
      'name'       => 'pelf_venture_details',
      'table_name' => 'civicrm_pelf_venture_details',
    ];
    $allParams = [
      "title"                       => "Venture details",
      "extends"                     => "Case",
      "extends_entity_column_value" => [ $case_id ],
      "style"                       => "Inline",
      "collapse_display"            => "0",
      "is_active"                   => "1",
      "collapse_adv_display"        => "0",
      "is_reserved"                 => "1",
      "is_public"                   => "0",
    ];
    $custom_fieldset_id = $this->createOrUpdate('CustomGroup', $baseParams, $allParams);

    // Now add liklihood adjustment percentage.
    $baseParams = [
      'custom_group_id' => "pelf_venture_details",
      'name'            => "pelf_worth_percent",
    ];
    $allParams = [
      'column_name'     => "worth_percent",
      'label'           => "Percentage scale",
      'data_type'       => "Float",
      'html_type'       => "Text",
      'is_required'     => 1,
    ];
    $worth_percent_field_id = $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // We need our allocation option group.
    $baseParams = [
      'name' => "pelf_project",
    ];
    $allParams = [
      'title'     => "Project",
      'data_type' => "Integer",
      'is_active' => 1,
    ];
    $this->createOrUpdate('OptionGroup', $baseParams, $allParams);

    // We need the 'unallocated' option.
    $baseParams = [
      'option_group_id' => "pelf_project",
      'name'            => "unallocated",
    ];
    $allParams = [
      'label'           => "Unallocated",
      'value'           => 1,
    ];
    $this->createOrUpdate('OptionValue', $baseParams, $allParams);

  }

  /**
   * Helper function
   *
   * @param string $entity e.g. Contact
   * @param array $baseParams used to find the entity
   * @param array $allParams used to update details of the entity. Added to $baseParams for new entities.
   *
   * @return int ID of thing created.
   */
  protected function createOrUpdate($entity, $baseParams, $allParams) {
    $results = civicrm_api3($entity, 'get', $baseParams);

    $params = $allParams;
    $debug_details = '';
    $count = $results['count'];
    if ($count === 1) {
      $params['id'] = $results['id'];
      $debug_details = 'update';
    }
    elseif ($count === 0) {
      // Create.
      $params += $baseParams;
      $debug_details = 'create';
    }
    elseif ($count > 1) {
      throw new Exception("Found multiple $entity entities matching: " . json_encode($baseParams));
    }
    $id = (int) civicrm_api3($entity, 'create', $params)['id'] ?? NULL;
    if (!$id) {
      throw new \Exception("Failed to $debug_details '$entity' entity with: " . json_encode($params));
    }
    return  $id;
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   *
  public function uninstall() {
   $this->executeSqlFile('sql/myuninstall.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled.
   *
  public function enable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is disabled.
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   *
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  } // */


  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = E::ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

}
