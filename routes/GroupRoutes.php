<?php

/**
 * Group & GroupType API Routes – v1
 *
 * Complete ministry group management system:
 *
 * CHURCH GROUPS (e.g., Choir, Youth, Ushering, Media Team)
 * • Full lifecycle: create → update → delete
 * • Automatic leader membership on creation
 * • Membership management (add/remove members)
 * • Paginated listing with powerful filtering
 * • Role-aware retrieval with member count
 *
 * GROUP TYPES (e.g., Worship, Service, Fellowship)
 * • Simple taxonomy management
 * • Uniqueness enforcement
 * • Deletion protection when in use
 *
 * Business Rules:
 * • Group leader is automatically added as first member
 * • Cannot remove the group leader via membership endpoint
 * • Cannot delete a group with members or messages
 * • Cannot delete a group type currently assigned to groups
 *
 * Essential for organizing volunteers, discipleship, and ministry coordination.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Group.php';
require_once __DIR__ . '/../core/GroupType.php';

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

   // =================================================================
   // CHURCH GROUPS
   // =================================================================

   // CREATE GROUP
   $method === 'POST' && $path === 'group/create' => (function () use ($token) {
      Auth::checkPermission($token, 'manage_groups');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = Group::create($payload);
      echo json_encode($result);
   })(),

   // UPDATE GROUP
   $method === 'PUT' && $pathParts[0] === 'group' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_groups');

      $groupId = $pathParts[2];
      if (!is_numeric($groupId)) {
            Helpers::sendFeedback('Valid Group ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = Group::update((int)$groupId, $payload);
      echo json_encode($result);
   })(),

   // DELETE GROUP
   $method === 'DELETE' && $pathParts[0] === 'group' && ($pathParts[1] ?? '') === 'delete' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_groups');

      $groupId = $pathParts[2];
      if (!is_numeric($groupId)) {
            Helpers::sendFeedback('Valid Group ID required', 400);
      }

      $result = Group::delete((int)$groupId);
      echo json_encode($result);
   })(),

   // VIEW SINGLE GROUP
   $method === 'GET' && $pathParts[0] === 'group' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_groups');

      $groupId = $pathParts[2];
      if (!is_numeric($groupId)) {
            Helpers::sendFeedback('Valid Group ID required', 400);
      }

      $group = Group::get((int)$groupId);
      echo json_encode($group);
   })(),

   // LIST ALL GROUPS (Paginated + Multi-Filter)
   $method === 'GET' && $path === 'group/all' => (function () use ($token) {
      Auth::checkPermission($token, 'view_groups');

      $page  = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

      $filters = [];
      if (!empty($_GET['type_id']) && is_numeric($_GET['type_id']))   $filters['type_id'] = (int)$_GET['type_id'];
      if (!empty($_GET['branch_id']) && is_numeric($_GET['branch_id'])) $filters['branch_id'] = (int)$_GET['branch_id'];
      if (!empty($_GET['name']))                                      $filters['name'] = trim($_GET['name']);

      $result = Group::getAll($page, $limit, $filters);
      echo json_encode($result);
   })(),

   // ADD MEMBER TO GROUP
   $method === 'POST' && $pathParts[0] === 'group' && ($pathParts[1] ?? '') === 'addMember' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_groups');

      $groupId = $pathParts[2];
      if (!is_numeric($groupId)) {
            Helpers::sendFeedback('Valid Group ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || empty($payload['member_id'])) {
            Helpers::sendFeedback('member_id is required', 400);
      }

      $result = Group::addMember((int)$groupId, (int)$payload['member_id']);
      echo json_encode($result);
   })(),

   // REMOVE MEMBER FROM GROUP
   $method === 'DELETE' && $pathParts[0] === 'group' && ($pathParts[1] ?? '') === 'removeMember' && isset($pathParts[2], $pathParts[3]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_groups');

      $groupId  = $pathParts[2];
      $memberId = $pathParts[3];
      if (!is_numeric($groupId) || !is_numeric($memberId)) {
            Helpers::sendFeedback('Valid Group ID and Member ID required', 400);
      }

      $result = Group::removeMember((int)$groupId, (int)$memberId);
      echo json_encode($result);
   })(),

   // GET GROUP MEMBERS (Paginated)
   $method === 'GET' && $pathParts[0] === 'group' && ($pathParts[1] ?? '') === 'members' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_groups');

      $groupId = $pathParts[2];
      if (!is_numeric($groupId)) {
            Helpers::sendFeedback('Valid Group ID required', 400);
      }

      $page  = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

      $result = Group::getMembers((int)$groupId, $page, $limit);
      echo json_encode($result);
   })(),

   // =================================================================
   // GROUP TYPES
   // =================================================================

   // CREATE GROUP TYPE
   $method === 'POST' && $path === 'grouptype/create' => (function () use ($token) {
      Auth::checkPermission($token, 'manage_group_types');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || empty($payload['name'])) {
            Helpers::sendFeedback('name is required', 400);
      }

      $result = GroupType::create($payload);
      echo json_encode($result);
   })(),

   // UPDATE GROUP TYPE
   $method === 'PUT' && $pathParts[0] === 'grouptype' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_group_types');

      $typeId = $pathParts[2];
      if (!is_numeric($typeId)) {
            Helpers::sendFeedback('Valid GroupType ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = GroupType::update((int)$typeId, $payload);
      echo json_encode($result);
   })(),

   // DELETE GROUP TYPE
   $method === 'DELETE' && $pathParts[0] === 'grouptype' && ($pathParts[1] ?? '') === 'delete' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_group_types');

      $typeId = $pathParts[2];
      if (!is_numeric($typeId)) {
            Helpers::sendFeedback('Valid GroupType ID required', 400);
      }

      $result = GroupType::delete((int)$typeId);
      echo json_encode($result);
   })(),

   // VIEW SINGLE GROUP TYPE
   $method === 'GET' && $pathParts[0] === 'grouptype' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_group_types');

      $typeId = $pathParts[2];
      if (!is_numeric($typeId)) {
            Helpers::sendFeedback('Valid GroupType ID required', 400);
      }

      $type = GroupType::get((int)$typeId);
      echo json_encode($type);
   })(),

   // LIST ALL GROUP TYPES
   $method === 'GET' && $path === 'grouptype/all' => (function () use ($token) {
      Auth::checkPermission($token, 'view_group_types');

      $page  = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

      $result = GroupType::getAll($page, $limit);
      echo json_encode($result);
   })(),

   // FALLBACK
   default => Helpers::sendFeedback('Group/GroupType endpoint not found', 404),
};