<?php

/**
 * Fiscal Year API Routes – v1
 *
 * Complete fiscal year lifecycle management with strict financial integrity:
 *
 * Key Capabilities:
 * • Create new fiscal years with automatic overlap protection
 * • Update fiscal year boundaries (only while open)
 * • Safely delete fiscal years with no associated transactions
 * • Close fiscal year (irreversible — locks all financial data)
 * • View single fiscal year with branch context
 * • Powerful paginated listing with multi-filter support
 *
 * Business Rules Enforced:
 * • Only one active fiscal year per branch at a time
 * • No overlapping date ranges allowed
 * • Cannot modify dates of a closed fiscal year
 * • Cannot delete fiscal year with budgets, contributions, or expenses
 *
 * Critical for audit compliance, financial reporting, and year-end processes.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/FiscalYear.php';

// ---------------------------------------------------------------------
// AUTHENTICATION & AUTHORIZATION
// ---------------------------------------------------------------------
$token = Auth::getBearerToken();
if (!$token || Auth::verify($token) === false) {
   Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

// ---------------------------------------------------------------------
// ROUTE DISPATCHER
// ---------------------------------------------------------------------
match (true) {

   // CREATE NEW FISCAL YEAR
   $method === 'POST' && $path === 'fiscalyear/create' => (function () use ($token) {
      Auth::checkPermission($token, 'manage_fiscal_years');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = FiscalYear::create($payload);
      echo json_encode($result);
   })(),

   // UPDATE FISCAL YEAR
   $method === 'POST' && $pathParts[0] === 'fiscalyear' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_fiscal_years');

      $fiscalYearId = $pathParts[2];
      if (!is_numeric($fiscalYearId)) {
         Helpers::sendFeedback('Valid Fiscal Year ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = FiscalYear::update((int)$fiscalYearId, $payload);
      echo json_encode($result);
   })(),

   // DELETE FISCAL YEAR (Only if no financial records)
   $method === 'POST' && $pathParts[0] === 'fiscalyear' && ($pathParts[1] ?? '') === 'delete' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_fiscal_years');

      $fiscalYearId = $pathParts[2];
      if (!is_numeric($fiscalYearId)) {
         Helpers::sendFeedback('Valid Fiscal Year ID required', 400);
      }

      $result = FiscalYear::delete((int)$fiscalYearId);
      echo json_encode($result);
   })(),

   // VIEW SINGLE FISCAL YEAR
   $method === 'GET' && $pathParts[0] === 'fiscalyear' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_fiscal_years');

      $fiscalYearId = $pathParts[2];
      if (!is_numeric($fiscalYearId)) {
         Helpers::sendFeedback('Valid Fiscal Year ID required', 400);
      }

      $fiscalYear = FiscalYear::get((int)$fiscalYearId);
      echo json_encode($fiscalYear);
   })(),

   // LIST ALL FISCAL YEARS (Paginated + Multi-Filter)
   $method === 'GET' && $path === 'fiscalyear/all' => (function () use ($token) {
      Auth::checkPermission($token, 'view_fiscal_years');

      $page  = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

      $filters = [];
      if (isset($_GET['branch_id']) && is_numeric($_GET['branch_id']))   $filters['branch_id'] = (int)$_GET['branch_id'];
      if (isset($_GET['status']) && in_array($_GET['status'], ['Active', 'Closed'])) $filters['status'] = $_GET['status'];
      if (!empty($_GET['date_from']))  $filters['date_from'] = $_GET['date_from'];
      if (!empty($_GET['date_to']))    $filters['date_to']   = $_GET['date_to'];

      $result = FiscalYear::getAll($page, $limit, $filters);
      echo json_encode($result);
   })(),

   // CLOSE FISCAL YEAR (Irreversible)
   $method === 'POST' && $pathParts[0] === 'fiscalyear' && ($pathParts[1] ?? '') === 'close' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_fiscal_years');

      $fiscalYearId = $pathParts[2];
      if (!is_numeric($fiscalYearId)) {
         Helpers::sendFeedback('Valid Fiscal Year ID required', 400);
      }

      $result = FiscalYear::close((int)$fiscalYearId);
      echo json_encode($result);
   })(),

   // FALLBACK
   default => Helpers::sendFeedback('Fiscal year endpoint not found', 404),
};