<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Finance.php';
require_once __DIR__ . '/../core/Helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$token = Auth::getBearerToken();
$pathParts = explode('/', trim($path, '/'));

if (!$token || !Auth::verify($token)) {
   Helpers::sendError('Unauthorized', 401);
}

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
   case 'GET financial/income_statement':
      Auth::checkPermission($token, 'view_financial_reports');
      $fiscalYearId = $_GET['fiscal_year_id'] ?? null;
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

   case 'GET financial/budget_vs_actual':
      Auth::checkPermission($token, 'view_financial_reports');
      $fiscalYearId = $_GET['fiscal_year_id'] ?? null;
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
