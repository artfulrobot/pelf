<?php
if (php_sapi_name() !== 'cli') {
  exit;
}

try {
$data = [
  'funders' => [
    'funder_a' => [
      'name' => 'Trickle Down Foundation',
    ],
    'funder_b' => [
      'name' => 'Spoils of Extractavism fund',
    ],
  ],
  'staff' => [
    'staff_a' => [ 'display_name' => 'Wilma', ],
    'staff_b' => [ 'display_name' => 'Betty', ],
    'staff_c' => [ 'display_name' => 'Barney', ],
  ],
  'projects' => [
    'unallocated' => 1,
    'activism' => 2,
  ],
  'cases' => [
    'case_a' => [
      'subject'       => 'Climate Emergency national action',
      'client'        => 'funder_a',
      'coordinator'   => 'staff_a',
      // case_type_id => 'pelf_venture'
      'status_id'     => 'Open', // this is a default, we could use our own..
      'worth_percent' => '50',
      'allocations' => [
        ['amount' => 1000, 'fy' => '2020-04-06', 'project' => 'unallocated'],
      ]
    ],
    'case_b' => [
      'subject'       => 'Not too late to go green',
      'client'        => 'funder_b',
      'coordinator'   => 'staff_b',
      // case_type_id => 'pelf_venture'
      'status_id'     => 'Open', // this is a default, we could use our own..
      'worth_percent' => '10',
      'allocations' => [
        ['amount' => 3000, 'fy' => '2019-04-06', 'project' => 'unallocated'],
        ['amount' => 2000, 'fy' => '2020-04-06', 'project' => 'unallocated'],
        ['amount' => 1000, 'fy' => '2020-04-06', 'project' => 'activism'],
      ]
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

// Lookup the case type id
$venture_case_type_id = civicrm_api3('CaseType', 'getvalue', ['return' => 'id', 'name' => 'pelf_venture']);

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
      'case_type_id'  => $venture_case_type_id,
      'creator_id'    => $data['staff'][$case['coordinator']]['id'],
      $worth_field_id => $case['worth_percent'],
    ];
    $id = civicrm_api3('case', 'create', $params)['id'] ?? NULL;

    foreach ($case['allocations'] as $allocation) {
      civicrm_api3('PelfFundsAllocation', 'create', [
        'case_id' => $id,
        'amount' => $allocation['amount'],
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
