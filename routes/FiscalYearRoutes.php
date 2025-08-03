<?php

/**
 * Fiscal Year API Routes
 * This file handles the routing for fiscal year management, including creation, updating, deletion, and retrieval.
 * It checks for authentication and permissions before processing requests.
 * It uses the FiscalYear model for database interactions and returns JSON responses.
 * Requires authentication via a Bearer token and appropriate permissions.
 */
$pathParts = explode('/', trim($path, '/'));
$action = isset($pathParts[1]) ? $pathParts[1] : '';
$param = isset($pathParts[2]) ? $pathParts[2] : null;

try {
   switch ("$method $action") {
      case 'POST fiscalyear':
         Auth::checkPermission($token, 'manage_fiscal_year');
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(FiscalYear::create($data));
         break;

      case 'PUT fiscalyear':
         Auth::checkPermission($token, 'manage_fiscal_year');
         if (!$param) {
            throw new Exception('Fiscal year ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(FiscalYear::update($param, $data));
         break;

      case 'DELETE fiscalyear':
         Auth::checkPermission($token, 'manage_fiscal_year');
         if (!$param) {
            throw new Exception('Fiscal year ID required');
         }
         echo json_encode(FiscalYear::delete($param));
         break;

      case 'GET fiscalyear':
         Auth::checkPermission($token, 'view_fiscal_year');
         if (!$param) {
            throw new Exception('Fiscal year ID required');
         }
         echo json_encode(FiscalYear::get($param));
         break;

      case 'GET fiscalyears':
         Auth::checkPermission($token, 'view_fiscal_year');
         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
         $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
         $filters = [];
         if (isset($_GET['branch_id'])) {
            $filters['branch_id'] = $_GET['branch_id'];
         }
         if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
         }
         if (isset($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
         }
         if (isset($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
         }
         echo json_encode(FiscalYear::getAll($page, $limit, $filters));
         break;

      case 'POST fiscalyear/close':
         Auth::checkPermission($token, 'manage_fiscal_year');
         if (!$param) {
            throw new Exception('Fiscal year ID required');
         }
         echo json_encode(FiscalYear::close($param));
         break;

      default:
         Helpers::sendFeedback('Endpoint not found', 404);
   }
} catch (Exception $e) {
   Helpers::sendFeedback($e->getMessage(), 400);
}
