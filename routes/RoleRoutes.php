<?php

/**
 * Roles and Permissions API Routes
 * This file handles the routing for role and permission management, including creation, updating, deletion, and retrieval.
 * It checks for authentication and permissions before processing requests.
 * It uses the Role and Permission models for database interactions and returns JSON responses.
 * Requires authentication via a Bearer token and appropriate permissions.
 */
require_once __DIR__ . '/Role.php';
require_once __DIR__ . '/Permission.php';
$action = isset($pathParts[1]) ? $pathParts[1] : '';
$param = isset($pathParts[2]) ? $pathParts[2] : null;

try {
   switch ("$method $action") {
      case 'POST role':
         Auth::checkPermission($token, 'manage_roles');
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(Role::create($data));
         break;

      case 'PUT role':
         Auth::checkPermission($token, 'manage_roles');
         if (!$param) {
            throw new Exception('Role ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(Role::update($param, $data));
         break;

      case 'DELETE role':
         Auth::checkPermission($token, 'manage_roles');
         if (!$param) {
            throw new Exception('Role ID required');
         }
         echo json_encode(Role::delete($param));
         break;

      case 'GET role':
         Auth::checkPermission($token, 'view_roles');
         if (!$param) {
            throw new Exception('Role ID required');
         }
         echo json_encode(Role::get($param));
         break;

      case 'GET roles':
         Auth::checkPermission($token, 'view_roles');
         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
         $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
         $filters = [];
         if (isset($_GET['name'])) {
            $filters['name'] = $_GET['name'];
         }
         echo json_encode(Role::getAll($page, $limit, $filters));
         break;

      case 'POST role/permissions':
         Auth::checkPermission($token, 'manage_roles');
         if (!$param) {
            throw new Exception('Role ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         if (!isset($data['permission_id'])) {
            throw new Exception('Permission ID required');
         }
         echo json_encode(Role::assignPermission($param, $data['permission_id']));
         break;

      case 'DELETE role/permissions':
         Auth::checkPermission($token, 'manage_roles');
         if (!$param || !isset($pathParts[4])) {
            throw new Exception('Role ID and Permission ID required');
         }
         echo json_encode(Role::removePermission($param, $pathParts[4]));
         break;

      case 'POST permission':
         Auth::checkPermission($token, 'manage_permissions');
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(Permission::create($data));
         break;

      case 'PUT permission':
         Auth::checkPermission($token, 'manage_permissions');
         if (!$param) {
            throw new Exception('Permission ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(Permission::update($param, $data));
         break;

      case 'DELETE permission':
         Auth::checkPermission($token, 'manage_permissions');
         if (!$param) {
            throw new Exception('Permission ID required');
         }
         echo json_encode(Permission::delete($param));
         break;

      case 'GET permission':
         Auth::checkPermission($token, 'view_permissions');
         if (!$param) {
            throw new Exception('Permission ID required');
         }
         echo json_encode(Permission::get($param));
         break;

      case 'GET permission/all':
         Auth::checkPermission($token, 'view_permissions');
         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
         $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
         $filters = [];
         if (isset($_GET['name'])) {
            $filters['name'] = $_GET['name'];
         }
         echo json_encode(Permission::getAll($page, $limit, $filters));
         break;

      case 'POST role/assign':
         Auth::checkPermission($token, 'manage_roles');
         if (!$param) {
            throw new Exception('Member ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         if (!isset($data['role_id'])) {
            throw new Exception('Role ID required');
         }
         echo json_encode(Role::assignToMember($param, $data['role_id']));
         break;

      case 'DELETE role/remove':
         Auth::checkPermission($token, 'manage_roles');
         if (!$param) {
            throw new Exception('Member ID required');
         }
         echo json_encode(Role::removeFromMember($param));
         break;

      default:
         Helpers::sendFeedback('Endpoint not found', 404);
         break;
   }
} catch (Exception $e) {
   Helpers::sendFeedback($e->getMessage(), 400);
}
