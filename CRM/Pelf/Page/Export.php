<?php
use CRM_Pelf_ExtensionUtil as E;

class CRM_Pelf_Page_Export extends CRM_Core_Page {

  public function run() {

    // Check case types.
    $pelf = pelf();

    $params = [];
    if (preg_match('/^(\d+)$/', $_GET['caseType'] ?? '')) {
      $params['case_type_id'] = $_GET['caseType'];
    }
    $data = $pelf->getBrowseData($params, ['withActivities' => FALSE]);
    /*
      'clients'         => $clients,
      'caseTypes'       => $this->caseTypes,
      'cases'           => $cases,
      'case_statuses'   => $case_statuses,
      'projects'        => $projects,
      'financial_years' => $financial_years,
      'totals'          => $totals,
      'currencySymbol'  => $this->currencySymbol,
     */

    // We need to flatten financial year and projects.
    $extraCols = [];
    $extraColsSimple = [];
    $projects = $data['projects'];
    $simple = TRUE;

    foreach ($data['cases'] as &$case) {
      $uniqueProjects = [];
      foreach ($case['funds'] as $line) {
        $projectName = $projects[$line['project']]['label'];
        $uniqueProjects[$projectName] = TRUE;

        // Pivot
        $key = "$line[fy_start]: $projectName";
        if (!isset($extraCols[$key])) {
          $extraCols[$key] = [];
        }
        if (!isset($extraCols[$key][$case['id']])) {
          $extraCols[$key][$case['id']] = 0;
        }
        $extraCols[$key][$case['id']] += (float) $line['amount'];

        // Now the simple version
        $key = $line['fy_start'];
        if (!isset($extraColsSimple[$key])) {
          $extraColsSimple[$key] = [];
        }
        if (!isset($extraCols[$key][$case['id']])) {
          $extraColsSimple[$key][$case['id']] = 0;
        }
        $extraColsSimple[$key][$case['id']] += (float) $line['amount'];
      }
      // Also store a list of projects.
      ksort($uniqueProjects);
      $case['projects'] = implode(', ', array_keys($uniqueProjects));
      if (count($uniqueProjects) > 1) {
        $simple = FALSE;
      }
    }
    unset($case);
    if ($simple) {
      $extraCols = $extraColsSimple;
    }
    ksort($extraCols);

    $cols = [
      'Client',
      'Name',
      'Stage',
      'Project',
      'Value',
      'Scaling percentage',
      'Scaled Value',
    ];

    $rows = [array_merge($cols, array_keys($extraCols))];
    $clients = $data['clients'];
    $stageNames = $data['case_statuses'];
    foreach ($data['cases'] as $case) {
      $row = [
        // Client(s') name(s):
        implode(', ',  array_map(function ($clientId) use ($clients) { return $clients[$clientId]['display_name']; }, $case['clients'])),
        // Case subject
        $case['subject'],
        // Stage
        $stageNames[$case['status_id']]['label'],
        // Project(s)
        $case['projects'],
        // Value (total)
        (float) $case['funds_total'],
        // Percentage.
        (float) $case['worth_percent'],
        // Scaled
        (int) (((float) $case['worth_percent']) / 100 * ((float) $case['funds_total'])),
      ];

      // Now do financial columns.
      foreach ($extraCols as $col) {
        $row[] = $col[$case['id']] ?? 0;
      }

      $rows[] = $row;
    }

    // Create CSV from output.
    $csv = '';

    $csvEncode = function ($_) {
      if (is_numeric($_)) {
        return $_;
      }
      return '"' . str_replace('"', '""', $_) . '"';
    };

    foreach ($rows as $row) {
      $row = array_map($csvEncode, $row);
      $csv .= implode(',', $row) . "\n";
    }

    header("Content-Length: " . strlen($csv));
    header("Content-Type: text/csv");
    header('Content-Disposition: attachment; filename=Pelf-report-' . date('Y-m-d H:i') . '.csv');
    header('Content-Description: File Transfer');
    header('Expires: 0');
    print $csv;
    exit;
  }

}
