<?php

/**
 * Expense API Routes – RESTful & Convention-Compliant
 *
 * Endpoints:
 * /expense/create
 * /expense/view/{id}
 * /expense/all
 * /expense/review/{id}
 * /expense/cancel/{id}
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

require_once __DIR__ . '/../core/Expense.php';

if (!$token || !Auth::verify($token)) {
   Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

$action     = $pathParts[1] ?? '';
$resourceId = $pathParts[2] ?? null;

switch ("$method $action") {

   case 'POST create':
      Auth::checkPermission($token, 'create_expense');
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }
      $result = Expense::create($payload);
      echo json_encode($result);
      break;

   case 'GET view':
      Auth::checkPermission($token, 'view_expenses');
      if (!$resourceId || !is_numeric($resourceId)) {
         Helpers::sendFeedback('Expense ID is required in URL', 400);
      }
      $expense = Expense::get((int)$resourceId);
      echo json_encode($expense);
      break;

   case 'GET all':
      Auth::checkPermission($token, 'view_expenses');
      $page   = max(1, (int)($_GET['page'] ?? 1));
      $limit  = max(1, min(100, (int)($_GET['limit'] ?? 10)));
      $filters = [];
      foreach (['fiscal_year_id', 'branch_id', 'category_id', 'status', 'start_date', 'end_date'] as $key) {
         if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $filters[$key] = $_GET[$key];
         }
      }
      $result = Expense::getAll($page, $limit, $filters);
      echo json_encode($result);
      break;

   case 'POST review':
      Auth::checkPermission($token, 'approve_expenses');
      if (!$resourceId || !is_numeric($resourceId)) {
         Helpers::sendFeedback('Expense ID is required in URL', 400);
      }
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload || !in_array($payload['action'] ?? '', ['approve', 'reject'], true)) {
         Helpers::sendFeedback('Valid "action" (approve|reject) required', 400);
      }
      $result = Expense::review(
         (int)$resourceId,
         $payload['action'],
         $payload['remarks'] ?? null
      );
      echo json_encode($result);
      break;

   case 'POST cancel':
      Auth::checkPermission($token, 'cancel_expenses');
      if (!$resourceId || !is_numeric($resourceId)) {
         Helpers::sendFeedback('Expense ID is required in URL', 400);
      }
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload || empty(trim($payload['reason'] ?? ''))) {
         Helpers::sendFeedback('Cancellation reason is required', 400);
      }
      $result = Expense::cancel((int)$resourceId, trim($payload['reason']));
      echo json_encode($result);
      break;

   default:
      Helpers::sendFeedback('Expense endpoint not found', 404);
}