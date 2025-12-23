<?php

/**
 * Expense API Routes – v1
 *
 * Full expense lifecycle with approval workflow:
 * - Create expense request
 * - View single expense
 * - Paginated listing with powerful filtering
 * - Review (approve/reject)
 * - Cancel pending expense
 *
 * All operations strictly permission-controlled.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Expense.php';

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

   // CREATE EXPENSE REQUEST
   $method === 'POST' && $path === 'expense/create' => (function () use ($token) {
      Auth::checkPermission($token, 'create_expense');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = Expense::create($payload);
      echo json_encode($result);
   })(),

   // VIEW SINGLE EXPENSE
   $method === 'GET' && $pathParts[0] === 'expense' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_expenses');

      $expenseId = $pathParts[2];
      if (!is_numeric($expenseId)) {
         Helpers::sendFeedback('Valid Expense ID required', 400);
      }

      $expense = Expense::get((int)$expenseId);
      echo json_encode($expense);
   })(),

   // LIST ALL EXPENSES (Paginated + Filtered)
   $method === 'GET' && $path === 'expense/all' => (function () use ($token) {
      Auth::checkPermission($token, 'view_expenses');

      $page  = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

      $filters = [];
      foreach (
         [
            'fiscal_year_id',
            'branch_id',
            'category_id',
            'status',
            'start_date',
            'end_date'
         ] as $key
      ) {
         if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $filters[$key] = $_GET[$key];
         }
      }

      $result = Expense::getAll($page, $limit, $filters);
      echo json_encode($result);
   })(),

   // REVIEW EXPENSE (Approve/Reject)
   $method === 'POST' && $pathParts[0] === 'expense' && ($pathParts[1] ?? '') === 'review' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'approve_expenses');

      $expenseId = $pathParts[2];
      if (!is_numeric($expenseId)) {
         Helpers::sendFeedback('Valid Expense ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || !in_array($payload['action'] ?? '', ['approve', 'reject'], true)) {
         Helpers::sendFeedback('Valid "action" (approve|reject) required', 400);
      }

      $result = Expense::review(
         (int)$expenseId,
         $payload['action'],
         $payload['remarks'] ?? null
      );
      echo json_encode($result);
   })(),

   // CANCEL PENDING EXPENSE
   $method === 'POST' && $pathParts[0] === 'expense' && ($pathParts[1] ?? '') === 'cancel' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'cancel_expenses');

      $expenseId = $pathParts[2];
      if (!is_numeric($expenseId)) {
         Helpers::sendFeedback('Valid Expense ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || empty(trim($payload['reason'] ?? ''))) {
         Helpers::sendFeedback('Cancellation reason is required', 400);
      }

      $result = Expense::cancel((int)$expenseId, trim($payload['reason']));
      echo json_encode($result);
   })(),

   // FALLBACK
   default => Helpers::sendFeedback('Expense endpoint not found', 404),
};