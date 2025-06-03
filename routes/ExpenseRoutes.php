<?php

/**
 * Expense API Routes
 * This file handles all routes related to expenses, including creation, updating, deletion, and retrieval.
 * It checks for authentication and permissions before processing requests.
 * It uses the Expense model for database interactions and returns JSON responses.
 * Requires authentication via a Bearer token and appropriate permissions.
 */

if (!$token || !Auth::verify($token)) {
   Helpers::sendError('Unauthorized', 401);
}

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
   case 'POST expense/create':
      Auth::checkPermission($token, 'create_expense');
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = Expense::create($input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'PUT expense/update':
      Auth::checkPermission($token, 'create_expense');
      $expenseId = $pathParts[2] ?? null;
      if (!$expenseId) {
         Helpers::sendError('Expense ID required', 400);
      }
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = Expense::update($expenseId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'DELETE expense/delete':
      Auth::checkPermission($token, 'delete_expense');
      $expenseId = $pathParts[2] ?? null;
      if (!$expenseId) {
         Helpers::sendError('Expense ID required', 400);
      }
      try {
         $result = Expense::delete($expenseId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'GET expense/view':
      // Auth::checkPermission($token, 'view_expense');
      $expenseId = $pathParts[2] ?? null;
      if (!$expenseId) {
         Helpers::sendError('Expense ID required', 400);
      }
      try {
         $expense = Expense::get($expenseId);
         echo json_encode($expense);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 404);
      }
      break;

   case 'POST expense/approve':
      // Auth::checkPermission($token, 'approve_expense');
      $expenseId = $pathParts[2] ?? null;
      if (!$expenseId) {
         Helpers::sendError('Expense ID required', 400);
      }
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $decoded = Auth::verify($token);
         $result = Expense::approve($expenseId, $decoded['user_id'], $input['status'], $input['comments'] ?? null);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'GET expense/all':
      // Auth::checkPermission($token, 'view_expense');
      $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
      $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
      $filters = [];
      if (isset($_GET['fiscal_year_id']) && is_numeric($_GET['fiscal_year_id'])) {
         $filters['fiscal_year_id'] = intval($_GET['fiscal_year_id']);
      }
      if (isset($_GET['category_id']) && is_numeric($_GET['category_id'])) {
         $filters['category_id'] = intval($_GET['category_id']);
      }
      if (isset($_GET['status']) && in_array($_GET['status'], ['Pending Approval', 'Approved', 'Declined'])) {
         $filters['status'] = $_GET['status'];
      }
      try {
         $result = Expense::getAll($page, $limit, $filters);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'GET expense/report':
      // Auth::checkPermission($token, 'view_expense');

      $type = $pathParts[2] ?? null;

      if (!$type) {
         Helpers::sendError('Report type required', 400);
      }
      $filters = [];
      if (isset($_GET['fiscal_year_id'])) {
         $filters['fiscal_year_id'] = $_GET['fiscal_year_id'];
      }
      if (isset($_GET['year'])) {
         $filters['year'] = $_GET['year'];
      }
      try {
         $result = Expense::getReports($type, $filters);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   default:
      Helpers::sendError('Endpoint not found', 404);
}
