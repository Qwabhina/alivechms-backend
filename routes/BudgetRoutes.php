<?php

/**
 * Budget API Routes
 * This file handles budget-related API routes for the AliveChMS backend.
 * It includes routes for creating, updating, deleting, viewing, and listing budgets,
 * as well as submitting budgets for approval and approving budgets.
 * It uses the Budget class for business logic and the Auth class for permission checks.
 */
require_once __DIR__ . '/../core/Budget.php';

if (!$token || !Auth::verify($token))  Helpers::sendFeedback('Unauthorized', 401);

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
   case 'POST budget/create':
      // Auth::checkPermission($token, 'create_budget');

      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = Budget::create($input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'POST budget/update':
      // Auth::checkPermission($token, 'edit_budget');

      $budgetId = $pathParts[2] ?? null;
      if (!$budgetId) Helpers::sendFeedback('Budget ID required', 400);

      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = Budget::update($budgetId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'POST budget/delete':
      // Auth::checkPermission($token, 'delete_budget');

      $budgetId = $pathParts[2] ?? null;
      if (!$budgetId) Helpers::sendFeedback('Budget ID required', 400);

      try {
         $result = Budget::delete($budgetId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'GET budget/view':
      // Auth::checkPermission($token, 'view_budget');

      $budgetId = $pathParts[2] ?? null;
      if (!$budgetId) Helpers::sendFeedback('Budget ID required', 400);

      try {
         $budget = Budget::get($budgetId);
         echo json_encode($budget);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), 404);
      }
      break;

   case 'GET budget/all':
      // Auth::checkPermission($token, 'view_budgets');

      $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
      $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
      $filters = [];

      if (isset($_GET['FiscalYear'])) $filters['FiscalYear'] = $_GET['FiscalYear'];
      if (isset($_GET['Branch'])) $filters['Branch'] = $_GET['Branch'];
      if (isset($_GET['Status'])) $filters['Status'] = $_GET['Status'];

      try {
         $result = Budget::getAll($page, $limit, $filters);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'POST budget/submit':
      // Auth::checkPermission($token, 'edit_budget');

      $budgetId = $pathParts[2] ?? null;
      if (!$budgetId) Helpers::sendFeedback('Budget ID required', 400);

      $input = json_decode(file_get_contents('php://input'), true);
      if (!isset($input['approvers']) || !is_array($input['approvers']) || empty($input['approvers'])) Helpers::sendFeedback('Approver(s) required', 400);

      try {
         $result = Budget::submitForApproval($budgetId, $input['approvers']);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;
   case 'POST budget/approve':
      // Auth::checkPermission($token, 'approve_budget');

      $approvalId = $pathParts[2] ?? null;
      if (!$approvalId) Helpers::sendFeedback('Approval ID required', 400);

      $input = json_decode(file_get_contents('php://input'), true);
      if (!isset($input['status']) || !in_array($input['status'], ['Approved', 'Rejected'])) Helpers::sendFeedback('Valid status (Approved or Rejected) required', 400);

      try {
         $result = Budget::approve($approvalId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;
   default:
      Helpers::sendFeedback('Request Malfformed', 405);
      break;
}
