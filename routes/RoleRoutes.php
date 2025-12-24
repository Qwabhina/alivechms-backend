<?php

/**
 * Role & Permission Management API Routes – v1
 *
 * The beating heart of AliveChMS Role-Based Access Control (RBAC):
 *
 * ROLES (e.g., Pastor, Elder, Treasurer, Admin, Member, Guest)
 * • Full lifecycle: create → update → delete (with safety)
 * • Bulk permission assignment (atomic replace-all)
 * • Role-to-member assignment
 * • Rich retrieval with full permission tree
 *
 * PERMISSIONS
 * • Managed via Permission.php (separate file)
 * • Atomic building blocks of authority
 *
 * Business & Spiritual Governance:
 * • Deletion blocked if role assigned to any member
 * • Permission changes are immediate and system-wide
 * • Full audit trail via logs
 * • Designed for stewardship, accountability, and biblical leadership structure
 *
 * Critical for:
 * • Protecting financial data
 * • Safeguarding member privacy
 * • Enforcing leadership hierarchy
 * • Preventing unauthorized access
 *
 * "Let every person be subject to the governing authorities..." — Romans 13:1
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Role.php';

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

   // CREATE ROLE
   $method === 'POST' && $path === 'role/create' => (function () use ($token) {
      Auth::checkPermission('manage_roles');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = Role::create($payload);
      echo json_encode($result);
   })(),

   // UPDATE ROLE
   $method === 'PUT' && $pathParts[0] === 'role' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($pathParts) {
      Auth::checkPermission('manage_roles');

      $roleId = $pathParts[2];
      if (!is_numeric($roleId)) {
         Helpers::sendFeedback('Valid Role ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = Role::update((int)$roleId, $payload);
      echo json_encode($result);
   })(),

   // DELETE ROLE
   $method === 'DELETE' && $pathParts[0] === 'role' && ($pathParts[1] ?? '') === 'delete' && isset($pathParts[2]) => (function () use ($pathParts) {
      Auth::checkPermission('manage_roles');

      $roleId = $pathParts[2];
      if (!is_numeric($roleId)) {
         Helpers::sendFeedback('Valid Role ID required', 400);
      }

      $result = Role::delete((int)$roleId);
      echo json_encode($result);
   })(),

   // VIEW SINGLE ROLE (with full permission tree)
   $method === 'GET' && $pathParts[0] === 'role' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($pathParts) {
      Auth::checkPermission('view_roles');

      $roleId = $pathParts[2];
      if (!is_numeric($roleId)) {
         Helpers::sendFeedback('Valid Role ID required', 400);
      }

      $role = Role::get((int)$roleId);
      echo json_encode($role);
   })(),

   // LIST ALL ROLES (with permissions)
   $method === 'GET' && $path === 'role/all' => (function () use ($token) {
      Auth::checkPermission('view_roles');

      $result = Role::getAll();
      echo json_encode($result);
   })(),

   // ASSIGN PERMISSIONS TO ROLE (Replace All)
   $method === 'POST' && $pathParts[0] === 'role' && ($pathParts[1] ?? '') === 'permissions' && isset($pathParts[2]) => (function () use ($pathParts) {
      Auth::checkPermission('manage_roles');

      $roleId = $pathParts[2];
      if (!is_numeric($roleId)) {
         Helpers::sendFeedback('Valid Role ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || empty($payload['permission_ids']) || !is_array($payload['permission_ids'])) {
         Helpers::sendFeedback('permission_ids array is required', 400);
      }

      $result = Role::assignPermissions((int)$roleId, $payload['permission_ids']);
      echo json_encode($result);
   })(),

   // ASSIGN ROLE TO MEMBER
   $method === 'POST' && $pathParts[0] === 'role' && ($pathParts[1] ?? '') === 'assign' && isset($pathParts[2]) => (function () use ($pathParts) {
      Auth::checkPermission('manage_roles');

      $memberId = $pathParts[2];
      if (!is_numeric($memberId)) {
         Helpers::sendFeedback('Valid Member ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || empty($payload['role_id'])) {
         Helpers::sendFeedback('role_id is required', 400);
      }

      $result = Role::assignToMember((int)$memberId, (int)$payload['role_id']);
      echo json_encode($result);
   })(),

   // FALLBACK
   default => Helpers::sendFeedback('Role endpoint not found', 404),
};