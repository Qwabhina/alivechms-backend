<?php

/**
 * Finance Reporting API Routes – RESTful & Convention-Compliant
 *
 * Endpoints:
 * /finance/income-statement/{fiscalYearId}
 * /finance/budget-vs-actual/{fiscalYearId}
 * /finance/expense-summary/{fiscalYearId}
 * /finance/contribution-summary/{fiscalYearId}
 * /finance/balance-sheet/{fiscalYearId}
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

require_once __DIR__ . '/../core/Finance.php';

if (!$token || !Auth::verify($token)) {
   Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

$action        = $pathParts[1] ?? '';
$fiscalYearId  = $pathParts[2] ?? null;

if (!$fiscalYearId || !is_numeric($fiscalYearId)) {
   Helpers::sendFeedback('Fiscal Year ID is required in URL', 400);
}

$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to'] ?? null;

switch ("$method $action") {

   case 'GET income-statement':
      Auth::checkPermission($token, 'view_financial_reports');
      $report = Finance::getIncomeStatement((int)$fiscalYearId, $dateFrom, $dateTo);
      echo json_encode($report);
      break;

   case 'GET budget-vs-actual':
      Auth::checkPermission($token, 'view_financial_reports');
      $report = Finance::getBudgetVsActual((int)$fiscalYearId, $dateFrom, $dateTo);
      echo json_encode($report);
      break;

   case 'GET expense-summary':
      Auth::checkPermission($token, 'view_financial_reports');
      $report = Finance::getExpenseSummary((int)$fiscalYearId, $dateFrom, $dateTo);
      echo json_encode($report);
      break;

   case 'GET contribution-summary':
      Auth::checkPermission($token, 'view_financial_reports');
      $report = Finance::getContributionSummary((int)$fiscalYearId, $dateFrom, $dateTo);
      echo json_encode($report);
      break;

   case 'GET balance-sheet':
      Auth::checkPermission($token, 'view_financial_reports');
      $report = Finance::getBalanceSheet((int)$fiscalYearId, $dateFrom, $dateTo);
      echo json_encode($report);
      break;

   default:
      Helpers::sendFeedback('Finance endpoint not found', 404);
}