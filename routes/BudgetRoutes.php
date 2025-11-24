<?php

/**
 * Budget API Routes – Full Featured & Convention-Compliant
 *
 * RESTful endpoints exactly as requested:
 * - /budget/create
 * - /budget/update/{id}
 * - /budget/delete/{id}
 * - /budget/view/{id}
 * - /budget/all
 * - /budget/submit/{id}
 * - /budget/review/{id}
 * - /budget/item/add/{budgetId}
 * - /budget/item/update/{itemId}
 * - /budget/item/delete/{itemId}
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

require_once __DIR__ . '/../core/Budget.php';

// Authentication required for all budget routes
if (!$token || !Auth::verify($token)) {
   Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

// Extract path components
$action     = $pathParts[1] ?? '';        // create | update | view | all | submit | review | item
$resourceId = $pathParts[2] ?? null;       // BudgetID or ItemID
$subAction  = $pathParts[3] ?? null;       // add | update | delete (for items)

// ---------------------------------------------------------------------
// BUDGET LEVEL ENDPOINTS
// ---------------------------------------------------------------------
if ($action !== 'item') {

   switch ("$method $action") {

      case 'POST create':
         Auth::checkPermission($token, 'create_budgets');
         $payload = json_decode(file_get_contents('php://input'), true);
         if (!$payload) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
         }
         $result = Budget::create($payload);
         echo json_encode($result);
         break;

      case 'PUT update':
         Auth::checkPermission($token, 'edit_budgets');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Budget ID is required in URL', 400);
         }
         $payload = json_decode(file_get_contents('php://input'), true) ?? [];
         $result = Budget::update((int)$resourceId, $payload);
         echo json_encode($result);
         break;

      case 'DELETE delete':
         Auth::checkPermission($token, 'delete_budgets');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Budget ID is required in URL', 400);
         }
         // Soft-delete or hard-delete based on your policy (currently not implemented)
         Helpers::sendFeedback('Budget deletion not yet implemented', 501);
         break;

      case 'GET view':
         Auth::checkPermission($token, 'view_budgets');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Budget ID is required in URL', 400);
         }
         $budget = Budget::get((int)$resourceId);
         echo json_encode($budget);
         break;

      case 'GET all':
         Auth::checkPermission($token, 'view_budgets');
         $page   = max(1, (int)($_GET['page'] ?? 1));
         $limit  = max(1, min(100, (int)($_GET['limit'] ?? 10)));
         $filters = [];
         foreach (['fiscal_year_id', 'branch_id', 'status'] as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
               $filters[$key] = $_GET[$key];
            }
         }
         $result = Budget::getAll($page, $limit, $filters);
         echo json_encode($result);
         break;

      case 'PUT submit':
         Auth::checkPermission($token, 'submit_budgets');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Budget ID is required in URL', 400);
         }
         $result = Budget::submitForApproval((int)$resourceId);
         echo json_encode($result);
         break;

      case 'POST review':
         Auth::checkPermission($token, 'approve_budgets');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Budget ID is required in URL', 400);
         }
         $payload = json_decode(file_get_contents('php://input'), true);
         if (!$payload || !in_array($payload['action'] ?? '', ['approve', 'reject'], true)) {
            Helpers::sendFeedback('Valid "action" (approve|reject) required', 400);
         }
         $result = Budget::review(
            (int)$resourceId,
            $payload['action'],
            $payload['remarks'] ?? null
         );
         echo json_encode($result);
         break;

      default:
         Helpers::sendFeedback('Budget endpoint not found', 404);
   }
   exit;
}

// ---------------------------------------------------------------------
// BUDGET ITEM ENDPOINTS (/budget/item/*)
// ---------------------------------------------------------------------
if ($action === 'item') {

   switch ("$method $subAction") {

      case 'POST add':
         Auth::checkPermission($token, 'edit_budgets');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Budget ID is required in URL', 400);
         }
         $payload = json_decode(file_get_contents('php://input'), true);
         if (!$payload) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
         }
         $result = Budget::addItem((int)$resourceId, $payload);
         echo json_encode($result);
         break;

      case 'PUT update':
         Auth::checkPermission($token, 'edit_budgets');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Item ID is required in URL', 400);
         }
         $payload = json_decode(file_get_contents('php://input'), true) ?? [];
         $result = Budget::updateItem((int)$resourceId, $payload);
         echo json_encode($result);
         break;

      case 'DELETE delete':
         Auth::checkPermission($token, 'edit_budgets');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Item ID is required in URL', 400);
         }
         $result = Budget::deleteItem((int)$resourceId);
         echo json_encode($result);
         break;

      default:
         Helpers::sendFeedback('Budget item endpoint not found', 404);
   }
   exit;
}

// Fallback
Helpers::sendFeedback('Invalid budget route', 404);