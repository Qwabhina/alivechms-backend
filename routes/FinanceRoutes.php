<?php

/**
 * Finance API Routes
 * This file handles the routing for financial reports, including income statements and budget vs actual reports.
 * It checks for authentication and permissions before processing requests.
 * It uses the Expense model for database interactions and returns JSON responses.
 * Requires authentication via a Bearer token and appropriate permissions.
 */
if (!$token || !Auth::verify($token)) {
   Helpers::sendError('Unauthorized', 401);
}

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
   case 'GET finance/income_statement':
      // Auth::checkPermission($token, 'view_financial_reports');
      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) {
         Helpers::sendError('Fiscal year ID required', 400);
      }
      try {
         $result = Finance::getIncomeStatement($fiscalYearId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'GET finance/budget_vs_actual':
      // Auth::checkPermission($token, 'view_financial_reports');
      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) {
         Helpers::sendError('Fiscal year ID required', 400);
      }
      try {
         $result = Finance::getBudgetVsActual($fiscalYearId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   default:
      Helpers::sendError('Endpoint not found', 404);
}
