<?php

/**
 * Expense Category API Routes – v1
 *
 * Complete taxonomy management for expense classification:
 *
 * PURPOSE IN THE CHURCH
 * • Enables accurate financial reporting by ministry area
 * • Critical for budgeting, auditing, and stewardship transparency
 * • Examples: Tithes & Offerings, Missions, Building Maintenance, Staff Salaries
 *
 * BUSINESS RULES
 * • Category names must be unique system-wide
 * • Cannot delete a category currently used in any expense
 * • Simple, clean, high-performance CRUD
 *
 * "Moreover it is required of stewards that they be found faithful." — 1 Corinthians 4:2
 *
 * This is the foundation of trustworthy financial stewardship.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/ExpenseCategory.php';

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

   // CREATE EXPENSE CATEGORY
   $method === 'POST' && $path === 'expensecategory/create' => (function () use ($token) {
      Auth::checkPermission($token, 'manage_expense_categories');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = ExpenseCategory::create($payload);
      echo json_encode($result);
   })(),

   // UPDATE EXPENSE CATEGORY
   $method === 'PUT' && $pathParts[0] === 'expensecategory' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_expense_categories');

      $categoryId = $pathParts[2];
      if (!is_numeric($categoryId)) {
         Helpers::sendFeedback('Valid Category ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = ExpenseCategory::update((int)$categoryId, $payload);
      echo json_encode($result);
   })(),

   // DELETE EXPENSE CATEGORY
   $method === 'DELETE' && $pathParts[0] === 'expensecategory' && ($pathParts[1] ?? '') === 'delete' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_expense_categories');

      $categoryId = $pathParts[2];
      if (!is_numeric($categoryId)) {
         Helpers::sendFeedback('Valid Category ID required', 400);
      }

      $result = ExpenseCategory::delete((int)$categoryId);
      echo json_encode($result);
   })(),

   // VIEW SINGLE EXPENSE CATEGORY
   $method === 'GET' && $pathParts[0] === 'expensecategory' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_expense');

      $categoryId = $pathParts[2];
      if (!is_numeric($categoryId)) {
         Helpers::sendFeedback('Valid Category ID required', 400);
      }

      $category = ExpenseCategory::get((int)$categoryId);
      echo json_encode($category);
   })(),

   // LIST ALL EXPENSE CATEGORIES
   $method === 'GET' && $path === 'expensecategory/all' => (function () use ($token) {
      Auth::checkPermission($token, 'view_expense');

      $result = ExpenseCategory::getAll();
      echo json_encode($result);
   })(),

   // FALLBACK
   default => Helpers::sendFeedback('ExpenseCategory endpoint not found', 404),
};