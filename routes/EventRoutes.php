<?php

/**
 * Event API Routes – RESTful & Convention-Compliant
 *
 * Endpoints:
 * /event/create
 * /event/update/{id}
 * /event/delete/{id}
 * /event/view/{id}
 * /event/all
 * /event/attendance/bulk/{id}
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

require_once __DIR__ . '/../core/Event.php';

if (!$token || !Auth::verify($token)) {
   Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

$action     = $pathParts[1] ?? '';
$resourceId = $pathParts[2] ?? null;

switch ("$method $action") {

   case 'POST create':
      Auth::checkPermission($token, 'manage_events');
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload) Helpers::sendFeedback('Invalid JSON', 400);
      $result = Event::create($payload);
      echo json_encode($result);
      break;

   case 'PUT update':
      Auth::checkPermission($token, 'manage_events');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Event ID required', 400);
      $payload = json_decode(file_get_contents('php://input'), true) ?? [];
      $result = Event::update((int)$resourceId, $payload);
      echo json_encode($result);
      break;

   case 'DELETE delete':
      Auth::checkPermission($token, 'manage_events');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Event ID required', 400);
      $result = Event::delete((int)$resourceId);
      echo json_encode($result);
      break;

   case 'GET view':
      Auth::checkPermission($token, 'view_events');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Event ID required', 400);
      $event = Event::get((int)$resourceId);
      echo json_encode($event);
      break;

   case 'GET all':
      Auth::checkPermission($token, 'view_events');
      $page = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
      $filters = [];
      foreach (['branch_id', 'start_date', 'end_date'] as $key) {
         if (isset($_GET[$key]) && $_GET[$key] !== '') $filters[$key] = $_GET[$key];
      }
      $result = Event::getAll($page, $limit, $filters);
      echo json_encode($result);
      break;

   case 'POST attendance/bulk':
      Auth::checkPermission($token, 'record_attendance');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Event ID required', 400);
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload || empty($payload['attendances'])) Helpers::sendFeedback('attendances array required', 400);
      $result = Event::recordBulkAttendance((int)$resourceId, $payload);
      echo json_encode($result);
      break;

   case 'POST attendance/single':
      Auth::checkPermission($token, 'record_attendance');
      if (!$resourceId || !is_numeric($resourceId)) {
         Helpers::sendFeedback('Event ID required in URL', 400);
      }
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload || empty($payload['member_id'])) {
         Helpers::sendFeedback('member_id is required', 400);
      }
      $status = $payload['status'] ?? 'Present';
      $result = Event::recordSingleAttendance(
         (int)$resourceId,
         (int)$payload['member_id'],
         $status
      );
      echo json_encode($result);
      break;

   default:
      Helpers::sendFeedback('Event endpoint not found', 404);
}