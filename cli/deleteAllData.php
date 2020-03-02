<?php
if (php_sapi_name() !== 'cli') {
  exit;
}
function deleteActivities($params) {
  $activities = civicrm_api3('Activity', 'get', $params)['values'] ?? [];
  foreach ($activities as $activity) {
    civicrm_api3('Activity', 'delete', ['id' => $activity['id']]);
  }
}

try {

    // {{{
      $tpls = <<<JSON
{
  "name": "pelf_grant",
  "title": "Grant application",
  "statuses": [
    {
      "name": "pelf_research",
      "label": "Research",
      "description": "Looking into this possible prospect.",
      "color": "#C1EECB",
      "phase": "prospect",
      "grouping": "Opened",
      "weight": 10
    },
    {
      "name": "pelf_writing",
      "label": "Writing bid",
      "description": "Preparing to submit bid",
      "color": "#A3E2B0",
      "phase": "prospect",
      "grouping": "Opened",
      "weight": 30
    },
    {
      "name": "pelf_submitted",
      "label": "Submitted bid",
      "description": "Bid submitted, waiting to hear",
      "color": "#85D596",
      "phase": "prospect",
      "grouping": "Opened",
      "weight": 40
    },
    {
      "name": "pelf_negotiate",
      "label": "Negotiating",
      "description": "Near completion, discussing terms/contract etc.",
      "color": "#85D596",
      "phase": "prospect",
      "grouping": "Opened",
      "weight": 50
    },
    {
      "name": "pelf_contract",
      "label": "Contract",
      "description": "Success: Contract agreed",
      "color": "#4bbbdd",
      "phase": "live",
      "grouping": "Opened",
      "weight": 80
    },
    {
      "name": "pelf_dropped",
      "label": "Dropped",
      "description": "We decided not to proceed. e.g. research revealed unsuitable",
      "color": "#888888",
      "phase": "dropped",
      "grouping": "Closed",
      "weight": 90
    },
    {
      "name": "pelf_failed",
      "label": "Failed",
      "description": "Bid was declined",
      "color": "#997777",
      "phase": "failed",
      "grouping": "Closed",
      "weight": 100
    },
    {
      "name": "pelf_completed",
      "label": "Completed",
      "description": "Contract has been delivered and is now all finished.",
      "color": "#7F9977",
      "phase": "complete",
      "grouping": "Closed",
      "weight": 110
    }
  ]
}
JSON;
      //}}}

    // Get all standard pelf case types
    $case_types = civicrm_api3('CaseType', 'get', ['return' => ['id', 'name'], 'options' => ['limit' =>0 ]])['values'] ?? [];
    $pelf_case_types = [];
    foreach ($case_types as $_) {
      if (substr($_['name'], 0, 4) === 'pelf') {
        $pelf_case_types[$_['id']] = $_['name'];
      }
    }

    // Delete all pelf cases.
    if (!empty($pelf_case_types)) {
      $cases = civicrm_api3('Case', 'get', [
        'return' => ['id'],
        'case_type_id' => ['IN' => array_keys($pelf_case_types)],
        'options' => ['limit' => 0],
      ]);
      foreach ($cases['values'] ?? [] as $row) {

        // First delete activities.
        deleteActivities([ 'case_id' => $row['id'] ]);

        print "deleting case $row[id]\n";
        civicrm_api3('Case', 'delete', ['id' => $row['id']]);
      }
    }

    // Delete all pelf statuses
    $case_statuses = Civi\Api4\OptionValue::get()
      ->addWhere('option_group.name', '=', 'case_status')
      ->addWhere('name', 'LIKE', 'pelf_%')
      ->setCheckPermissions(FALSE)
      ->execute();
    foreach ($case_statuses as $_) {
      Civi\Api4\OptionValue::delete()
        ->setCheckPermissions(FALSE)
        ->addWhere('id', '=',$_['id'])
        ->execute();
    }

    // Delete all pelf case types
    foreach ($pelf_case_types as $id => $name) {
      $case_types = civicrm_api3('CaseType', 'delete', ['id' => $id]);
      print "deleted Case Type $name\n";
    }

    // @todo delete projects
    try {
      Civi\Api4\OptionValue::delete()
        ->addWhere('option_group.name', '=', 'pelf_project')
        ->setCheckPermissions(FALSE)
        ->execute();
    }
    catch (API_Exception $e) {
      if (!preg_match('/Cannot delete OptionValue, no records found/', $e->getMessage())) {
        throw $e;
      }
    }

  $data = [
    'funders' => [
      'Trickle Down Foundation',
      'Spoils of Extractavism fund',
    ],
    'staff' => [
      'Wilma',
      'Betty',
      'Barney',
      'Wilma Test',
      'Betty Test',
      'Barney Test',
    ],
  ];

  // Delete funders.
  foreach ($data['funders'] as $display_name) {
    // exists?

    $contacts = civicrm_api3('contact', 'get', [
      'display_name' => $display_name,
      'contact_type' => 'Organization',
      'return' => ['id'],
    ])['values'] ?? [];
    foreach ($contacts as $contact) {
      $id = $contact['id'];
      // delete activities.
      try {
        \Civi\Api4\Activity::delete()
          ->addWhere('activity_contacts.contact_id', '=', $id)
          ->setCheckPermissions(FALSE)
          ->execute();
      }
      catch (API_Exception $e) {
        if (!preg_match('/no records found/', $e->getMessage())) throw $e;
      }

      civicrm_api3('contact', 'delete', [
        'skip_undelete' => 1, 'id' => $id ]);
      print "Deleted contact $id: $display_name\n";
    }
  }

  // Delete staff.
  foreach ($data['staff'] as $display_name) {
    // exists?
    $contacts = civicrm_api3('contact', 'get', [
      'display_name' => $display_name,
      'contact_type' => 'Individual',
      'return' => ['id'],
    ])['values'] ?? [];
    foreach ($contacts as $contact) {
      $id = $contact['id'];
      // delete activities.
      try {
        \Civi\Api4\Activity::delete()
          ->addWhere('activity_contacts.contact_id', '=', $id)
          ->setCheckPermissions(FALSE)
          ->execute();
      }
      catch (API_Exception $e) {
        if (!preg_match('/no records found/', $e->getMessage())) throw $e;
      }
      civicrm_api3('contact', 'delete', [
        'skip_undelete' => 1, 'id' => $id ]);
      print "Deleted staff contact $id: $display_name\n";
    }
  }

  // Get rid of projects.
  foreach (civicrm_api3('OptionValue', 'get', ['option_group_id' => 'pelf_project'])['values'] ?? [] as $opt) {
    civicrm_api3('OptionValue', 'delete', ['id' => $opt['id']]);
    print "Deleted pelf project $opt[id]: $opt[name]\n";
  }
}
catch (\Exception $e) {
  print get_class($e) . " " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
