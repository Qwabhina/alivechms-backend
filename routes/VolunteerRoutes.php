<?php

/**
 * Volunteer Management API Routes
 *
 * Endpoints:
 * /volunteer/role/create
 * /volunteer/role/all
 * /volunteer/assign/{eventId}
 * /volunteer/confirm/{assignmentId}
 * /volunteer/complete/{assignmentId}
 * /volunteer/event/{eventId}
 * /volunteer/remove/{assignmentId}
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-22
 */

require_once __DIR__ . '/../core/Volunteer.php';

if (!$token || !Auth::verify($token)) {
   Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

$action       = $pathParts[1] ?? '';
$resourceId   = $pathParts[2] ?? null;

switch ("$method $action") {

   // Volunteer Roles
   case 'POST role/create':
      Auth::checkPermission($token, 'manage_volunteer_roles');
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload) Helpers::sendFeedback('Invalid JSON', 400);
      $result = Volunteer::createRole($payload);
      echo json_encode($result);
      break;

   case 'GET role/all':
      $result = Volunteer::getRoles();
      echo json_encode(['data' => $result]);
      break;

   // Assignments
   case 'POST assign':
      Auth::checkPermission($token, 'assign_volunteers');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Event ID required', 400);
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload || empty($payload['volunteers'])) Helpers::sendFeedback('volunteers array required', 400);
      $result = Volunteer::assign((int)$resourceId, $payload['volunteers']);
      echo json_encode($result);
      break;

   case 'POST confirm':
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Assignment ID required', 400);
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload || !in_array($payload['action'] ?? '', ['confirm', 'decline'])) {
         Helpers::sendFeedback("action must be 'confirm' or 'decline'", 400);
      }
      $result = Volunteer::confirmAssignment((int)$resourceId, $payload['action']);
      echo json_encode($result);
      break;

   case 'POST complete':
      Auth::checkPermission($token, 'manage_volunteers');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Assignment ID required', 400);
      $result = Volunteer::completeAssignment((int)$resourceId);
      echo json_encode($result);
      break;

   case 'GET event':
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Event ID required', 400);
      $page = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
      $result = Volunteer::getByEvent((int)$resourceId, $page, $limit);
      echo json_encode($result);
      break;

   case 'DELETE remove':
      Auth::checkPermission($token, 'manage_volunteers');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Assignment ID required', 400);
      $result = Volunteer::remove((int)$resourceId);
      echo json_encode($result);
      break;

   default:
      Helpers::sendFeedback('Volunteer endpoint not found', 404);
}
