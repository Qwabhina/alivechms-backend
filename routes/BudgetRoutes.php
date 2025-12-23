<?php

/**
 * Budget API Routes
 *
 * Full budget lifecycle with line-item management and approval workflow:
 * - Create draft budget with items
 * - Update draft budget
 * - Submit for approval
 * - Review (approve/reject)
 * - View single budget with items
 * - Paginated listing with filters
 * - Line-item CRUD (add/update/delete)
 *
 * All operations strictly permission-controlled.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Budget.php';

// ---------------------------------------------------------------------
// AUTHENTICATION & AUTHORIZATION
// ---------------------------------------------------------------------
$token = Auth::getBearerToken();
if (!$token || Auth::verify($token) === false) Helpers::sendError('Unauthorized: Valid token required', 401);

// ---------------------------------------------------------------------
// ROUTE DISPATCHER
// ---------------------------------------------------------------------
match (true) {

   // CREATE BUDGET (with items)
   $method === 'POST' && $path === 'budget/create' => (function () use ($token) {
      Auth::checkPermission('create_budgets');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload))  Helpers::sendError('Invalid JSON payload', 400);

      $result = Budget::create($payload);
      echo json_encode($result);
   })(),

   // UPDATE BUDGET (title/description only, draft state)
   $method === 'PUT' && $pathParts[0] === 'budget' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($pathParts) {
      Auth::checkPermission('edit_budgets');

      $budgetId = $pathParts[2];
      if (!is_numeric($budgetId)) {
         Helpers::sendError('Valid Budget ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendError('Invalid JSON payload', 400);
      }

      $result = Budget::update((int)$budgetId, $payload);
      echo json_encode($result);
   })(),

   // SUBMIT BUDGET FOR APPROVAL
   $method === 'PUT' && $pathParts[0] === 'budget' && ($pathParts[1] ?? '') === 'submit' && isset($pathParts[2]) => (function () use ($pathParts) {
      Auth::checkPermission('submit_budgets');

      $budgetId = $pathParts[2];
      if (!is_numeric($budgetId)) {
         Helpers::sendError('Valid Budget ID required', 400);
      }

      $result = Budget::submitForApproval((int)$budgetId);
      echo json_encode($result);
   })(),

   // REVIEW BUDGET (Approve/Reject)
   $method === 'POST' && $pathParts[0] === 'budget' && ($pathParts[1] ?? '') === 'review' && isset($pathParts[2]) => (function () use ($pathParts) {
      Auth::checkPermission('approve_budgets');

      $budgetId = $pathParts[2];
      if (!is_numeric($budgetId)) {
         Helpers::sendError('Valid Budget ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || !in_array($payload['action'] ?? '', ['approve', 'reject'], true)) {
         Helpers::sendError('Valid "action" (approve|reject) required', 400);
      }

      $result = Budget::review(
         (int)$budgetId,
            $payload['action'],
            $payload['remarks'] ?? null
      );
      echo json_encode($result);
   })(),

   // VIEW SINGLE BUDGET (with items)
   $method === 'GET' && $pathParts[0] === 'budget' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($pathParts) {
      Auth::checkPermission('view_budgets');

      $budgetId = $pathParts[2];
      if (!is_numeric($budgetId)) {
         Helpers::sendError('Valid Budget ID required', 400);
      }

      $budget = Budget::get((int)$budgetId);
      echo json_encode($budget);
   })(),

   // LIST ALL BUDGETS (Paginated + Filtered)
   $method === 'GET' && $path === 'budget/all' => (function () use ($token) {
      Auth::checkPermission('view_budgets');

      $page  = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

      $filters = [];
      foreach (['fiscal_year_id', 'branch_id', 'status'] as $key) {
         if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $filters[$key] = $_GET[$key];
         }
      }

      $result = Budget::getAll($page, $limit, $filters);
      echo json_encode($result);
   })(),

   // ADD BUDGET ITEM
   $method === 'POST' && $pathParts[0] === 'budget' && ($pathParts[1] ?? '') === 'item' && ($pathParts[2] ?? '') === 'add' && isset($pathParts[3]) => (function () use ($pathParts) {
      Auth::checkPermission('edit_budgets');

      $budgetId = $pathParts[3];
      if (!is_numeric($budgetId)) {
         Helpers::sendError('Valid Budget ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendError('Invalid JSON payload', 400);
      }

      $result = Budget::addItem((int)$budgetId, $payload);
      echo json_encode($result);
   })(),

   // UPDATE BUDGET ITEM
   $method === 'PUT' && $pathParts[0] === 'budget' && ($pathParts[1] ?? '') === 'item' && ($pathParts[2] ?? '') === 'update' && isset($pathParts[3]) => (function () use ($pathParts) {
      Auth::checkPermission('edit_budgets');

      $itemId = $pathParts[3];
      if (!is_numeric($itemId)) {
         Helpers::sendError('Valid Item ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendError('Invalid JSON payload', 400);
      }

      $result = Budget::updateItem((int)$itemId, $payload);
      echo json_encode($result);
   })(),

   // DELETE BUDGET ITEM
   $method === 'DELETE' && $pathParts[0] === 'budget' && ($pathParts[1] ?? '') === 'item' && ($pathParts[2] ?? '') === 'delete' && isset($pathParts[3]) => (function () use ($pathParts) {
      Auth::checkPermission('edit_budgets');

      $itemId = $pathParts[3];
      if (!is_numeric($itemId)) {
         Helpers::sendError('Valid Item ID required', 400);
      }

      $result = Budget::deleteItem((int)$itemId);
      echo json_encode($result);
   })(),

   // FALLBACK
   default => Helpers::sendError('Budget endpoint not found', 404),
};