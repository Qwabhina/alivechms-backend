<?php

/**
 * Fiscal Year API Routes
 * This file handles fiscal year-related API routes for the AliveChMS backend.
 * It includes routes for creating, updating, deleting, viewing, and listing fiscal years,
 * as well as closing fiscal years.
 * It uses the FiscalYear class for business logic and the Auth class for permission checks.
 */
require_once __DIR__ . '/../core/FiscalYear.php';

if (!$token || !Auth::verify($token))  Helpers::sendFeedback('Unauthorized', 401);

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
   case 'POST fiscalyear/create':
      // Auth::checkPermission($token, 'manage_fiscal_year');

      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = FiscalYear::create($input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('Fiscal year create error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'POST fiscalyear/update':
      // Auth::checkPermission($token, 'manage_fiscal_year');

      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) Helpers::sendFeedback('Fiscal year ID required', 400);

      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = FiscalYear::update($fiscalYearId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('Fiscal year update error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'POST fiscalyear/delete':
      // Auth::checkPermission($token, 'manage_fiscal_year');

      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) Helpers::sendFeedback('Fiscal year ID required', 400);

      try {
         $result = FiscalYear::delete($fiscalYearId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('Fiscal year delete error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'GET fiscalyear/view':
      // Auth::checkPermission($token, 'view_fiscal_year');

      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) Helpers::sendFeedback('Fiscal year ID required', 400);

      try {
         $fiscalYear = FiscalYear::get($fiscalYearId);
         echo json_encode($fiscalYear);
      } catch (Exception $e) {
         Helpers::logError('Fiscal year get error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 404);
      }
      break;

   case 'GET fiscalyear/all':
      // Auth::checkPermission($token, 'view_fiscal_year');

      $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
      $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
      $filters = [];

      if (isset($_GET['branch_id'])) $filters['branch_id'] = $_GET['branch_id'];
      if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
      if (isset($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
      if (isset($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];

      try {
         $result = FiscalYear::getAll($page, $limit, $filters);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('Fiscal year getAll error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'POST fiscalyear/close':
      // Auth::checkPermission($token, 'manage_fiscal_year');

      $fiscalYearId = $pathParts[2] ?? null;
      if (!$fiscalYearId) Helpers::sendFeedback('Fiscal year ID required', 400);

      try {
         $result = FiscalYear::close($fiscalYearId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('Fiscal year close error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   default:
      Helpers::sendFeedback('Request Malformed', 405);
      break;
}