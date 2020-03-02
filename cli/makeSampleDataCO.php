<?php
if (php_sapi_name() !== 'cli') {
  exit;
}
try {

  // Case Type definition {{{
    $tpls = <<<JSON
{
  "name": "pelf_partnership",
  "title": "Partnership",
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
      "name": "pelf_approach",
      "label": "Approach",
      "description": "",
      "color": "#A3E2B0",
      "phase": "prospect",
      "grouping": "Opened",
      "weight": 30
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
      "name": "pelf_deal_proposed",
      "label": "Deal Proposed",
      "description": "A deal has been proposed.",
      "color": "#68C87B",
      "phase": "prospect",
      "weight": 60
    },
    {
      "name": "pelf_deal_agreed",
      "label": "Deal Agreed",
      "description": "Deal has been agreed, but contract not finalised yet.",
      "color": "#4ABB61",
      "phase": "prospect",
      "weight": 70
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
      "weight": 85
    }
  ]
}
JSON;
    //}}}

    // Cases etc. {{{
$data = [
  'funders' => [
    'greenpeace' => [ 'name' => 'Greenpeace', ],
    'guardian' => [ 'name' => 'Guardian', ],
    'bbc' => [ 'name' => 'BBC', ],
    'independent' => [ 'name' => 'The Independent', ],
    'wwf' => [ 'name' => 'WWF', ],
    'getty' => [ 'name' => 'Getty Images', ],
    'earthaidlive' => [ 'name' => 'Earth Aid Live', ],
  ],
  'staff' => [
    'staff_a' => [ 'display_name' => 'Wilma Test', ],
    'staff_b' => [ 'display_name' => 'Betty Test', ],
    'staff_c' => [ 'display_name' => 'Barney Test', ],
  ],
  'projects' => [ // values not used, just keys
    'Climate Visuals: Training' => 1,
    'Climate Visuals: Consultancy' => 2,
    'Climate Visuals: Sponsorship' => 3,
    'Other: Training & Consultancy' => 4,
    'Other: Citizens\' assembly' => 5,
  ],
  'cases' => [
    'case_a' => [
      'subject'       => 'Story Strategy Map partnership',
      'client'        => 'greenpeace',
      'coordinator'   => 'staff_a',
      'status_id'     => 'pelf_approach',
      'worth_percent' => '10',
      'allocations' => [
        ['amount' => 1000, 'fy' => '2020-04-06', 'project' => 'Climate Visuals: Consultancy'],
      ],
      'activities' => [
        ['activity_type' => 'Meeting', 'source_contact_id' => 'staff_b', 'assignee' => 'staff_b', 'subject' => 'Discuss project', 'date' => '2020-01-02 12:32:00' ],
      ]
    ],
    'case_b' => [
      'subject'       => 'This is just a test',
      'client'        => 'bbc',
      'coordinator'   => 'staff_b',
      'status_id'     => 'pelf_deal_proposed',
      'worth_percent' => '50',
      'allocations' => [
        ['amount' => 3000, 'fy' => '2019-04-06', 'project' => 'Climate Visuals: Training'],
        ['amount' => 2000, 'fy' => '2020-04-06', 'project' => 'Climate Visuals: Consultancy'],
        ['amount' => 500, 'fy' => '2020-04-06', 'project' => 'Climate Visuals: Training'],
      ]
    ],
    'case_c' => [
      'subject'       => 'Training and bespoke partnership',
      'client'        => 'wwf',
      'coordinator'   => 'staff_b',
      'status_id'     => 'pelf_approach',
      'worth_percent' => '10',
      'allocations' => [
        ['amount' => 5000, 'fy' => '2020-04-06', 'project' => 'Climate Visuals: Consultancy'],
      ]
    ],
    'case_d' => [
      'subject'       => 'Bespoke briefing paper',
      'client'        => 'guardian',
      'coordinator'   => 'staff_c',
      'status_id'     => 'pelf_negotiate',
      'worth_percent' => '50',
      'allocations' => [
        ['amount' => 1000, 'fy' => '2020-04-06', 'project' => 'Climate Visuals: Consultancy'],
      ]
    ],
    'case_e' => [
      'subject'       => 'Getty Images Climate Visuals Grant',
      'client'        => 'getty',
      'coordinator'   => 'staff_c',
      'status_id'     => 'pelf_contract',
      'worth_percent' => '100',
      'allocations' => [
        ['amount' => 2000, 'fy' => '2020-04-06', 'project' => 'Climate Visuals: Consultancy'],
      ]
    ],
    'case_lost' => [
      'subject'       => 'Messaging consultancy',
      'client'        => 'earthaidlive',
      'coordinator'   => 'staff_c',
      'status_id'     => 'pelf_failed',
      'worth_percent' => '10',
      'allocations' => [
        ['amount' => 2000, 'fy' => '2020-04-06', 'project' => 'Climate Visuals: Training'],
      ]
    ],

  ],
];
    // }}}

// Create Case type, statuses, config.
/*
$params=json_decode($tpls, TRUE);
if (!$params) {
  throw new \InvalidArgumentException("invalid tpl json");
}
pelf()->createCaseTypeTemplate($params);
*/

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
// We need unique values
$existing = \Civi\Api4\OptionValue::get()
      ->setSelect(['id', 'name', 'value'])
      ->addWhere('option_group.name', '=', 'pelf_project')
      ->setCheckPermissions(FALSE)
      ->execute();
$existing_names = $existing->column('name');
$existing_values = $existing->column('value');

$next_value = 1;
$getNextAvailableValue = function() use (&$next_value, &$existing_values) {
  while (in_array($next_value, $existing_values)) $next_value++;
  $existing_values[] = $next_value;
  return $next_value;
};
foreach (array_keys($data['projects']) as $name) {
  if (!in_array($name, $existing_names)) {
    $value = $getNextAvailableValue();
    print "Creating $name with value $value... ";
    civicrm_api3('OptionValue', 'create', ['option_group_id' => 'pelf_project', 'name' => $name, 'value' => $value]);
    print "OK\n";
  }
}

// Look up the API field name for our custom worth percent field
require_once 'CRM/Core/BAO/CustomField.php';
$worth_field_id = 'custom_' . CRM_Core_BAO_CustomField::getCustomFieldID('pelf_worth_percent', 'pelf_venture_details');

// Lookup the case type id for the case type in the $tpl
$case_type_id = civicrm_api3('CaseType', 'getvalue', ['return' => 'id', 'name' => $params['name']]);
print "case Type ID: $case_type_id\n";

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
      'case_type_id'  => $case_type_id,
      'creator_id'    => $data['staff'][$case['coordinator']]['id'],
      $worth_field_id => $case['worth_percent'],
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
  // Create activities.
  foreach ($case['activities'] as &$activity) {
    $params = [
      'activity_type_id'   => $activity['activity_type'],
      'activity_date_time' => $activity['date'],
      'subject'            => $activity['subject'],
      'source_contact_id'  => $data['staff'][$activity['source_contact_id']]['id'],
      'assignee_id'        => $data['staff'][$activity['assignee']]['id'],
      'target_id'          => $data['funders'][$case['client']]['id'],
      'case_id'            => $case['id'],
    ];
    print_r($params);
    $activity_id = civicrm_api3('Activity', 'create', $params)['id'] ?? NULL;
    $activity['id'] = $activity_id;
  }
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
