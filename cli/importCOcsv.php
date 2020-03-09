<?php
if (php_sapi_name() !== 'cli') {
  exit;
}
define('CO_PELF_PARTNERSHIP_CASE_TYPE', 4);

CRM_Core_Transaction::create()->run(function($tx) {
  try {
    // Ensure we have pelf_research - not one of our normal types.

    /*
{
  "stage": "0. On Hold",
  "huh": "",
  "Organisation": 1854,
  "Title": "Pitch deck partnership",
  "Value": "£10,000.00",
  "unknown": "Estimated",
  "fy": "20/21",
  "Likelihood": "",
  "dummy": "£0.00",
  "Project": "Climate Visuals: Consultancy",
  "Notes": "Awards launch delayed until Jan 2020, looking to solidify MOU before engaging"
}
     */

    // Look up the API field name for our custom worth percent field
    require_once 'CRM/Core/BAO/CustomField.php';
    $worth_field_id = 'custom_' . CRM_Core_BAO_CustomField::getCustomFieldID('pelf_worth_percent', 'pelf_venture_details');

    $rows= json_decode(file_get_contents("/home/rich/co_partnerships.json"), TRUE);

    // Get list of projects required.
    $existing = \Civi\Api4\OptionValue::get()
      ->setSelect(['id', 'name', 'value'])
      ->addWhere('option_group.name', '=', 'pelf_project')
      ->setCheckPermissions(FALSE)
      ->execute();

    $existing->indexBy('name');
    $byName = [];
    $value = 1;
    foreach ($existing as $_ => $item) {
      $byName[$_] = (int) $item['value'];
      $value = max($value, (int) $item['value']);
    }

    foreach ($rows as &$row) {
      $project = trim($row['Project']);
      if (!isset($byName[$project])) {
        // Create project.
        $value++;
        civicrm_api3('OptionValue', 'create', [
          'option_group_id' => 'pelf_project',
          'name'            => $project,
          'value'           => $value,
        ]);
        $byName[$project] = $value;
      }
      $row['project_value'] = $byName[$project];
    }


    foreach ($rows as $row) {
      $stage = [
        0 => 'pelf_hold',
        1 => 'pelf_identify', // xxx
        2 => 'pelf_research',
        3 => 'pelf_approach',
        4 => 'pelf_negotiate',
        5 => 'pelf_deal_proposed',
        6 => 'pelf_deal_agreed',
        7 => 'pelf_contract',
        8 => 'pelf_dropped',
      ][$row['stage'][0]] ?? '';
      if (!$stage) {
        echo "Failed stage on row: " . json_encode($row);
        throw new Exception('nah');
      }

      // Find the organisation. {{{
      $organisation = $row['Organisation'];
      if (!$organisation) {
        throw new \Exception("no org name in row: " . json_encode($row));
      }
      $org_id = NULL;
      foreach (['display_name', 'organization_name', 'legal_name'] as $by) {
        $result = civicrm_api3('Contact', 'get', [$by => $organisation, 'contact_type' => 'Organization']);
        $result = \Civi\Api4\Contact::get()
          ->setCheckPermissions(FALSE)
          ->addSelect('id', 'display_name')
          ->addWhere($by, '=', $organisation)
          ->setLimit(25)
          ->execute();
        if ($result->count() > 1) {
          throw new \Exception("org name '$organisation' matches several orgs $by");
        }
        if ($result->count() == 1) {
          $org_id = $result->first()['id'];
          print "Found $organisation [$org_id]\n";
          break;
        }
      }
      if (empty($org_id)) {
        // Create org.
        $org_id = current(civicrm_api3('Contact', 'create', [
          'contact_type'      => 'Organization',
          'organization_name' => $organisation,
        ])['values'])['id'];
        print "Created $organisation [$org_id]\n";
      }
      // }}}


      $likelihood = (float) $row['Likelihood'];
      if (!$likelihood) {
        // Default to 5%!
        $likelihood = 0.05;
      }
      $likelihood *= 100;

      // Create a case, unless one with that title exists.
      $params = [
        'subject'       => $row['Title'],
        'contact_id'    => $org_id,
      ];
      if (civicrm_api3('Case', 'getcount', $params) ?? 0) {
        continue;
      }


      $params = [
        'subject'       => $row['Title'],
        'contact_id'    => $org_id,
        'status_id'     => $stage,
        'case_type_id'  => CO_PELF_PARTNERSHIP_CASE_TYPE,
        'creator_id'    => 10, // Rich
        $worth_field_id => $likelihood,
      ];
      $case_id = civicrm_api3('Case', 'create', $params)['id'] ?? NULL;

      $fy_start = substr($row['fy'], 0, 2);
      // 1st april is their tax year.
      $fy_start = "20$fy_start-04-01";

      // Create an allocation.
      civicrm_api3('PelfFundsAllocation', 'create', [
        'case_id'  => $case_id,
        'amount'   => (float) preg_replace('/[£,]/', '', $row['Value']),
        'fy_start' => $fy_start,
        'project'  => $row['project_value'],
      ]);

    }

    // Note?
  }
  catch (\Exception $e) {
    print get_class($e) . " " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $tx->rollback();
  }

});
