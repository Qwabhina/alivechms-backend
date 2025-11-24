<?php

/**
 * Role & Permission Management API Routes
 *
 * Endpoints:
 * /role/create
 * /role/update/{id}
 * /role/delete/{id}
 * /role/view/{id}
 * /role/all
 * /role/permissions/{id}
 * /role/assign/{memberId}
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

require_once __DIR__ . '/../core/Role.php';

if (!$token || !Auth::verify($token)) {
   Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

$action     = $pathParts[1] ?? '';
$resourceId = $pathParts[2] ?? null;

switch ("$method $action") {

   case 'POST create':
      Auth::checkPermission($token, 'manage_roles');
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload) Helpers::sendFeedback('Invalid JSON', 400);
      $result = Role::create($payload);
      echo json_encode($result);
      break;

   case 'PUT update':
      Auth::checkPermission($token, 'manage_roles');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Role ID required', 400);
      $payload = json_decode(file_get_contents('php://input'), true) ?? [];
      $result = Role::update((int)$resourceId, $payload);
      echo json_encode($result);
      break;

   case 'DELETE delete':
      Auth::checkPermission($token, 'manage_roles');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Role ID required', 400);
      $result = Role::delete((int)$resourceId);
      echo json_encode($result);
      break;

   case 'GET view':
      Auth::checkPermission($token, 'view_roles');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Role ID required', 400);
      $role = Role::get((int)$resourceId);
      echo json_encode($role);
      break;

   case 'GET all':
      Auth::checkPermission($token, 'view_roles');
      $result = Role::getAll();
      echo json_encode($result);
      break;

   case 'POST permissions':
      Auth::checkPermission($token, 'manage_roles');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Role ID required', 400);
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload || empty($payload['permission_ids'])) {
         Helpers::sendFeedback('permission_ids array required', 400);
      }
      $result = Role::assignPermissions((int)$resourceId, $payload['permission_ids']);
      echo json_encode($result);
      break;

   case 'POST assign':
      Auth::checkPermission($token, 'manage_roles');
      if (!$resourceId || !is_numeric($resourceId)) Helpers::sendFeedback('Member ID required', 400);
      $payload = json_decode(file_get_contents('php://input'), true);
      if (!$payload || empty($payload['role_id'])) {
         Helpers::sendFeedback('role_id required', 400);
      }
      $result = Role::assignToMember((int)$resourceId, (int)$payload['role_id']);
      echo json_encode($result);
      break;

   default:
      Helpers::sendFeedback('Role endpoint not found', 404);
}