<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Budget.php';
require_once __DIR__ . '/../core/Helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$token = Auth::getBearerToken();
$pathParts = explode('/', trim($path, '/'));

if (!$token || !Auth::verify($token)) {
   Helpers::sendError('Unauthorized', 401);
}

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
   case 'POST budget/create':
      Auth::checkPermission($token, 'manage_budgets');
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = Budget::create($input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'PUT budget/update':
      Auth::checkPermission($token, 'manage_budgets');
      $budgetId = $pathParts[2] ?? null;
      if (!$budgetId) {
         Helpers::sendError('Budget ID required', 400);
      }
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = Budget::update($budgetId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'DELETE budget/delete':
      Auth::checkPermission($token, 'manage_budgets');
      $budgetId = $pathParts[2] ?? null;
      if (!$budgetId) {
         Helpers::sendError('Budget ID required', 400);
      }
      try {
         $result = Budget::delete($budgetId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'GET budget/view':
      Auth::checkPermission($token, 'view_financial_reports');
      $budgetId = $pathParts[2] ?? null;
      if (!$budgetId) {
         Helpers::sendError('Budget ID required', 400);
      }
      try {
         $budget = Budget::get($budgetId);
         echo json_encode($budget);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 404);
      }
      break;

   case 'GET budget/all':
      Auth::checkPermission($token, 'view_financial_reports');
      $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
      $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
      $filters = [];
      if (isset($_GET['fiscal_year_id'])) {
         $filters['fiscal_year_id'] = $_GET['fiscal_year_id'];
      }
      if (isset($_GET['branch_id'])) {
         $filters['branch_id'] = $_GET['branch_id'];
      }
      try {
         $result = Budget::getAll($page, $limit, $filters);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;
}
