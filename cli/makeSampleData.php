<?php
if (php_sapi_name() !== 'cli') {
  exit;
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

    // Create Case type, statuses, config.
    pelf()->createCaseTypeTemplate(json_decode($tpls, TRUE), FALSE);

$data = [
  'funders' => [
    'funder_a' => [ 'name' => 'Trickle Down Foundation', ],
    'funder_b' => [ 'name' => 'Spoils of Extractavism Fund', ],
    'funder_c' => [ 'name' => 'Mx Smith Foundation', ],
    'funder_d' => [ 'name' => 'Radical Soap Shop', ],
    'funder_e' => [ 'name' => 'Cotswold Philanthopists Trust', ],
  ],
  'staff' => [
    'staff_a' => [ 'display_name' => 'Wilma Test', ],
    'staff_b' => [ 'display_name' => 'Betty Test', ],
    'staff_c' => [ 'display_name' => 'Barney Test', ],
  ],
  'projects' => [
    'Unallocated' => 1,
    'Climate Change: divestment' => 2,
    'Climate Change: green jobs' => 3,
    'Climate Change: communication' => 4,
    'Sweatshops: garments' => 5,
    'Sweatshops: electronics' => 6,
  ],
  'cases' => [
    'case_a' => [
      'subject'       => 'Climate Emergency national action',
      'date'          => '2019-05-01',
      'client'        => 'funder_a',
      'coordinator'   => 'staff_a',
      'status_id'     => 'pelf_writing',
      'worth_percent' => '50',
      'allocations' => [
        ['amount' => 1000, 'fy' => '2020-04-06', 'project' => 'Climate Change: divestment'],
      ],
      'activities' => [
        [
          'type' => 'Meeting',
          'subject' => 'Chat about progress',
          'datetime' => '2020-01-02',
          'status' => 'Completed',
          'assignee' => 'staff_a',
        ],
        [
          'type' => 'Meeting',
          'subject' => 'Report back on Q1',
          'datetime' => '2020-08-06',
          'status' => 'Scheduled',
          'assignee' => 'staff_b',
        ],
      ],
    ],
    'case_b' => [
      'subject'       => 'Not too late to go green',
      'date'          => '2019-05-01',
      'client'        => 'funder_b',
      'coordinator'   => 'staff_b',
      'status_id'     => 'pelf_submitted',
      'worth_percent' => '10',
      'allocations' => [
        ['amount' => 3000, 'fy' => '2019-04-06', 'project' => 'Climate Change: communication'],
        ['amount' => 2000, 'fy' => '2020-04-06', 'project' => 'Climate Change: communication'],
        ['amount' => 1000, 'fy' => '2020-04-06', 'project' => 'Climate Change: divestment'],
      ],
      'activities' => [
        [
          'type' => 'Meeting',
          'subject' => 'Online discussion: is it?',
          'datetime' => '2019-06-02',
          'status' => 'Completed',
          'assignee' => 'staff_a',
        ],
        [
          'type' => 'Follow Up',
          'subject' => 'Reapply?',
          'datetime' => '2020-05-06',
          'status' => 'Scheduled',
          'assignee' => 'staff_b',
        ],
      ],
    ],

    'case_c' => [
      'subject'       => 'Local activism',
      'date'          => '2019-05-01',
      'client'        => 'funder_c',
      'coordinator'   => 'staff_a',
      'status_id'     => 'pelf_negotiate',
      'worth_percent' => '50',
      'allocations' => [
        ['amount' => 20000, 'fy' => '2019-04-06', 'project' => 'Sweatshops: electronics'],
        ['amount' => 15000, 'fy' => '2020-04-06', 'project' => 'Sweatshops: electronics'],
        ['amount' =>  5000, 'fy' => '2020-04-06', 'project' => 'Sweatshops: garments'],
      ],
      'activities' => [
        [
          'type' => 'Meeting',
          'subject' => 'Meet new trustee',
          'datetime' => '2019-06-02',
          'status' => 'Completed',
          'assignee' => 'staff_a',
        ],
        [
          'type' => 'Follow Up',
          'subject' => 'Financial report',
          'datetime' => '2020-06-01',
          'status' => 'Scheduled',
          'assignee' => 'staff_b',
        ],
      ],
    ],

    'case_d' => [
      'subject'       => 'Electronics Watch Affiliation',
      'date'          => '2019-05-01',
      'client'        => 'funder_d',
      'coordinator'   => 'staff_a',
      'status_id'     => 'pelf_contract',
      'worth_percent' => '100',
      'allocations' => [
        ['amount' => 15000, 'fy' => '2019-04-06', 'project' => 'Sweatshops: electronics'],
        ['amount' => 5000, 'fy' => '2019-04-06', 'project' => 'Unallocated'],
        ['amount' => 15000, 'fy' => '2020-04-06', 'project' => 'Sweatshops: electronics'],
        ['amount' => 5000, 'fy' => '2020-04-06', 'project' => 'Unallocated'],
      ],
      'activities' => [
        [
          'type' => 'Meeting',
          'subject' => 'Funder visited',
          'datetime' => '2019-06-02',
          'status' => 'Completed',
          'assignee' => 'staff_a',
        ],
        [
          'type' => 'Follow Up',
          'subject' => 'Invite to workshop',
          'datetime' => '2020-08-06',
          'status' => 'Scheduled',
          'assignee' => 'staff_b',
        ],
      ],
    ],

    'case_e' => [
      'date'          => '2019-05-01',
      'subject'       => 'Supply chain justice',
      'client'        => 'funder_e',
      'coordinator'   => 'staff_b',
      'status_id'     => 'pelf_contract',
      'worth_percent' => '100',
      'allocations' => [
        ['amount' => 5000,  'fy' => '2019-04-06', 'project' => 'Sweatshops: garments'],
        ['amount' => 15000, 'fy' => '2020-04-06', 'project' => 'Sweatshops: garments'],
      ],
      'activities' => [
        [
          'type'     => 'Meeting',
          'subject'  => 'Initial planning meeting',
          'datetime' => '2019-08-02',
          'status'   => 'Completed',
          'assignee' => 'staff_a',
        ],
      ],
    ],



  ],
];

// Create funders.
foreach ($data['funders'] as &$funder) {
  // exists?
  $id = civicrm_api3('contact', 'get', [
    'organization_name' => $funder['name'],
    'contact_type' => 'Organization',
  ])['id'] ?? NULL;

  if (!$id) {
    $id = civicrm_api3('contact', 'create', [
      'organization_name' => $funder['name'],
      'contact_type' => 'Organization',
      ])['id'];
  }
  $funder['id'] = $id;
  unset($funder);
}

// Create staff.
foreach ($data['staff'] as &$staff) {
  // exists?
  $id = civicrm_api3('contact', 'get', [
    'display_name' => $staff['display_name'],
    'contact_type' => 'Individual',
  ])['id'] ?? NULL;

  if (!$id) {
    $id = civicrm_api3('contact', 'create', [
      'display_name' => $staff['display_name'],
      'contact_type' => 'Individual',
      ])['id'];
  }
  $staff['id'] = $id;
  unset($staff);
}

// Create pelf projects
foreach ($data['projects'] as $name => $value) {
  if (civicrm_api3('OptionValue', 'getcount', ['option_group_id' => 'pelf_project', 'name' => $name]) == 0) {
    civicrm_api3('OptionValue', 'create', ['option_group_id' => 'pelf_project', 'name' => $name, 'value' => $value]);
  }
}

// Look up the API field name for our custom worth percent field
require_once 'CRM/Core/BAO/CustomField.php';
$worth_field_id = 'custom_' . CRM_Core_BAO_CustomField::getCustomFieldID('pelf_worth_percent', 'pelf_venture_details');

// Lookup the case type id for the pelf_grant
$grant_case_type_id = civicrm_api3('CaseType', 'getvalue', ['return' => 'id', 'name' => 'pelf_grant']);

// Look up activity types.


// Create cases
foreach ($data['cases'] as &$case) {
  $id = civicrm_api3('case', 'get', [
    'subject' => $case['subject'],
  ])['id'] ?? NULL;
  if (!$id) {
    // Create case
    $params = [
      'subject'       => $case['subject'],
      'contact_id'    => $data['funders'][$case['client']]['id'],
      'status_id'     => $case['status_id'],
      'case_type_id'  => $grant_case_type_id,
      'creator_id'    => $data['staff'][$case['coordinator']]['id'],
      $worth_field_id => $case['worth_percent'],
      'created_date'  => $case['date'],
      'start_date'    => $case['date'],
      'modified_date' => $case['date'],
    ];
    $id = civicrm_api3('case', 'create', $params)['id'] ?? NULL;

    foreach ($case['allocations'] as $allocation) {
      civicrm_api3('PelfFundsAllocation', 'create', [
        'case_id' => $id,
        'amount' => $allocation['amount'],
        'fy_start' => $allocation['fy'],
        'project' => $data['projects'][$allocation['project']],
      ]);
    }

    foreach ($case['activities'] ?? [] as $activity_idx => $activity) {
      $params = [
        'source_contact_id'  => $data['funders'][$case['client']]['id'],
        'activity_type_id'   => $activity['type'],
        'activity_status_id' => $activity['status'],
        'case_id'            => $id,
        'location'           => "pelf_test: case$id activity$activity_idx",
        'subject'            => $activity['subject'],
        'activity_date_time' => $activity['datetime'],
        'target_id'          => $data['funders'][$case['client']]['id'],
        'assignee_id'        => $data['staff'][$activity['assignee']]['id'],
      ];
      if (!civicrm_api3('activity', 'getcount', ['location' => $params['location']])) {
        $activity_id = civicrm_api3('activity', 'create', $params)['id'] ?? 'FAIL';
        print "Activity $params[location]: $activity_id\n";
      }
    }
  }
  $case['id'] = $id;
  // Check case contact (no, that's for clients)
  // Change case coordinator. @todo
  /*
  $params = [
    'case_id' => $id,
    'contact_id' => $data['staff'][$case['coordinator']]['id'],
  ];
  if (civicrm_api3('CaseContact', 'getcount', $params) == 0) {
    civicrm_api3('CaseContact', 'create', $params);
  }
   */
}
foreach ($data as $thing => $stuff) {
  foreach ($stuff as $a => $b) {
    print "$thing: $a: $b[id]\n";
  }
}

}
catch (\Exception $e) {
  print get_class($e) . " " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
