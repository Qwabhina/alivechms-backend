<?php

/**
 * Finance API Routes
 * This file handles routing for financial reports in the AliveChMS backend, including income statement,
 * budget vs actual, expense summary, contribution summary, and balance sheet. Supports date filtering.
 * It uses the Finance class for business logic and the Auth class for permission checks.
 */
require_once __DIR__ . '/../core/Finance.php';

if (!$token || !Auth::verify($token)) {
   Helpers::sendFeedback('Unauthorized', 401);
}

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
   case 'GET finance/incomeStatement':
      // Auth::checkPermission($token, 'view_financial_reports');
      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) {
         Helpers::sendFeedback('Fiscal year ID required', 400);
      }
      $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
      $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
      try {
         $result = Finance::getIncomeStatement($fiscalYearId, $dateFrom, $dateTo);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), $e->getCode() ?: 400);
      }
      break;

   case 'GET finance/budgetVsActual':
      // Auth::checkPermission($token, 'view_financial_reports');
      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) {
         Helpers::sendFeedback('Fiscal year ID required', 400);
      }
      $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
      $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
      try {
         $result = Finance::getBudgetVsActual($fiscalYearId, $dateFrom, $dateTo);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), $e->getCode() ?: 400);
      }
      break;

   case 'GET finance/expenseSummary':
      // Auth::checkPermission($token, 'view_financial_reports');
      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) {
         Helpers::sendFeedback('Fiscal year ID required', 400);
      }
      $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
      $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
      try {
         $result = Finance::getExpenseSummary($fiscalYearId, $dateFrom, $dateTo);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), $e->getCode() ?: 400);
      }
      break;

   case 'GET finance/contributionSummary':
      // Auth::checkPermission($token, 'view_financial_reports');
      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) {
         Helpers::sendFeedback('Fiscal year ID required', 400);
      }
      $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
      $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
      try {
         $result = Finance::getContributionSummary($fiscalYearId, $dateFrom, $dateTo);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), $e->getCode() ?: 400);
      }
      break;

   case 'GET finance/balanceSheet':
      // Auth::checkPermission($token, 'view_financial_reports');
      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) {
         Helpers::sendFeedback('Fiscal year ID required', 400);
      }
      $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
      $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
      try {
         $result = Finance::getBalanceSheet($fiscalYearId, $dateFrom, $dateTo);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), $e->getCode() ?: 400);
      }
      break;

   default:
      Helpers::sendFeedback('Request Malformed', 405);
      break;
}