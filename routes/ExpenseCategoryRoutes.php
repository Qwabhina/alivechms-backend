<?php

if (!$token || !Auth::verify($token)) Helpers::sendError('Unauthorized', 401);

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
   case 'POST expensecategory/create':
      Auth::checkPermission($token, 'manage_expense_categories');
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = ExpenseCategory::create($input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'PUT expensecategory/update':
      Auth::checkPermission($token, 'manage_expense_categories');
      $categoryId = $pathParts[2] ?? null;
      if (!$categoryId) {
         Helpers::sendError('Category ID required', 400);
      }
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = ExpenseCategory::update($categoryId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'DELETE expensecategory/delete':
      Auth::checkPermission($token, 'manage_expense_categories');
      $categoryId = $pathParts[2] ?? null;
      if (!$categoryId) {
         Helpers::sendError('Category ID required', 400);
      }
      try {
         $result = ExpenseCategory::delete($categoryId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'GET expensecategory/view':
      Auth::checkPermission($token, 'view_expense');
      $categoryId = $pathParts[2] ?? null;
      if (!$categoryId) {
         Helpers::sendError('Category ID required', 400);
      }
      try {
         $category = ExpenseCategory::get($categoryId);
         echo json_encode($category);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 404);
      }
      break;

   case 'GET expensecategory/all':
      Auth::checkPermission($token, 'view_expense');
      try {
         $categories = ExpenseCategory::getAll();
         echo json_encode($categories);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   default:
      Helpers::sendError('Endpoint not found', 404);
}
