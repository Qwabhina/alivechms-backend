<?php

/**
 * Pledge API Routes – RESTful & Convention-Compliant
 *
 * Endpoints:
 * /pledge/create
 * /pledge/view/{id}
 * /pledge/all
 * /pledge/payment/add/{pledgeId}
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

require_once __DIR__ . '/../core/Pledge.php';

if (!$token || !Auth::verify($token)) {
   Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

$action     = $pathParts[1] ?? '';
$resourceId = $pathParts[2] ?? null;

switch ("$method $action") {

   case 'POST create':
      Auth::checkPermission($token, 'manage_pledges');
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }
      $result = Pledge::create($payload);
      echo json_encode($result);
      break;

   case 'GET view':
      Auth::checkPermission($token, 'view_pledges');
      if (!$resourceId || !is_numeric($resourceId)) {
         Helpers::sendFeedback('Pledge ID is required in URL', 400);
      }
      $pledge = Pledge::get((int)$resourceId);
      echo json_encode($pledge);
      break;

   case 'GET all':
      Auth::checkPermission($token, 'view_pledges');
      $page   = max(1, (int)($_GET['page'] ?? 1));
      $limit  = max(1, min(100, (int)($_GET['limit'] ?? 10)));
      $filters = [];
      foreach (['member_id', 'status', 'fiscal_year_id'] as $key) {
         if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $filters[$key] = $_GET[$key];
         }
      }
      $result = Pledge::getAll($page, $limit, $filters);
      echo json_encode($result);
      break;

   case 'POST payment/add':
      Auth::checkPermission($token, 'record_pledge_payments');
      if (!$resourceId || !is_numeric($resourceId)) {
         Helpers::sendFeedback('Pledge ID is required in URL', 400);
      }
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }
      $result = Pledge::recordPayment((int)$resourceId, $payload);
      echo json_encode($result);
      break;

   default:
      Helpers::sendFeedback('Pledge endpoint not found', 404);
}
