<?php

/**
 * Group & GroupType API Routes
 *
 * Handles all operations for church groups and group types using clean RESTful paths:
 * - /group/* → Church groups
 * - /grouptype/* → Group type management
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

require_once __DIR__ . '/../core/Group.php';
require_once __DIR__ . '/../core/GroupType.php';

// All routes in this file require authentication
if (!$token || !Auth::verify($token)) {
   Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

$subPath = $pathParts[1] ?? '';  // e.g., 'create', 'view', 'all', 'addMember'
$resourceId = $pathParts[2] ?? null;
$extraId = $pathParts[3] ?? null; // Used for removeMember/{groupId}/{memberId}

// -----------------------------------------------------------------------------
// CHURCH GROUPS (/group/*)
// -----------------------------------------------------------------------------
if ($pathParts[0] === 'group') {

   switch ("$method $subPath") {

      case 'POST create':
         Auth::checkPermission($token, 'manage_groups');
         $input = json_decode(file_get_contents('php://input'), true);
         if (!$input) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
         }
         try {
            $result = Group::create($input);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
         }
         break;

      case 'PUT update':
         Auth::checkPermission($token, 'manage_groups');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Valid Group ID required', 400);
         }
         $input = json_decode(file_get_contents('php://input'), true);
         if (!$input) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
         }
         try {
            $result = Group::update((int)$resourceId, $input);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
         }
         break;

      case 'DELETE delete':
         Auth::checkPermission($token, 'manage_groups');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Valid Group ID required', 400);
         }
         try {
            $result = Group::delete((int)$resourceId);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
         }
         break;

      case 'GET view':
         Auth::checkPermission($token, 'view_groups');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Valid Group ID required', 400);
         }
         try {
            $group = Group::get((int)$resourceId);
            echo json_encode($group);
         } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 404);
         }
         break;

      case 'GET all':
         Auth::checkPermission($token, 'view_groups');
         $page   = max(1, (int)($_GET['page'] ?? 1));
         $limit  = max(1, min(100, (int)($_GET['limit'] ?? 10)));
         $filters = [];
         if (!empty($_GET['type_id']) && is_numeric($_GET['type_id']))   $filters['type_id'] = (int)$_GET['type_id'];
         if (!empty($_GET['branch_id']) && is_numeric($_GET['branch_id'])) $filters['branch_id'] = (int)$_GET['branch_id'];
         if (!empty($_GET['name']))                                     $filters['name'] = trim($_GET['name']);
         try {
            $result = Group::getAll($page, $limit, $filters);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback('Failed to retrieve groups', 400);
         }
         break;

      case 'POST addMember':
         Auth::checkPermission($token, 'manage_groups');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Valid Group ID required', 400);
         }
         $input = json_decode(file_get_contents('php://input'), true);
         if (!$input || empty($input['member_id'])) {
            Helpers::sendFeedback('member_id is required', 400);
         }
         try {
            $result = Group::addMember((int)$resourceId, (int)$input['member_id']);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
         }
         break;

      case 'DELETE removeMember':
         Auth::checkPermission($token, 'manage_groups');
         if (!$resourceId || !is_numeric($resourceId) || !$extraId || !is_numeric($extraId)) {
            Helpers::sendFeedback('Valid Group ID and Member ID required', 400);
         }
         try {
            $result = Group::removeMember((int)$resourceId, (int)$extraId);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
         }
         break;

      case 'GET members':
         Auth::checkPermission($token, 'view_groups');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Valid Group ID required', 400);
         }
         $page  = max(1, (int)($_GET['page'] ?? 1));
         $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
         try {
            $result = Group::getMembers((int)$resourceId, $page, $limit);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback('Failed to retrieve group members', 400);
         }
         break;

      default:
         Helpers::sendFeedback('Group endpoint not found', 404);
   }
   exit;
}

// -----------------------------------------------------------------------------
// GROUP TYPES (/grouptype/*)
// -----------------------------------------------------------------------------
if ($pathParts[0] === 'grouptype') {

   switch ("$method $subPath") {

      case 'POST create':
         Auth::checkPermission($token, 'manage_group_types');
         $input = json_decode(file_get_contents('php://input'), true);
         if (!$input || empty($input['name'])) {
            Helpers::sendFeedback('name is required', 400);
         }
         try {
            $result = GroupType::create($input);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
         }
         break;

      case 'PUT update':
         Auth::checkPermission($token, 'manage_group_types');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Valid GroupType ID required', 400);
         }
         $input = json_decode(file_get_contents('php://input'), true);
         if (!$input) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
         }
         try {
            $result = GroupType::update((int)$resourceId, $input);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
         }
         break;

      case 'DELETE delete':
         Auth::checkPermission($token, 'manage_group_types');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Valid GroupType ID required', 400);
         }
         try {
            $result = GroupType::delete((int)$resourceId);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
         }
         break;

      case 'GET view':
         Auth::checkPermission($token, 'view_group_types');
         if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Valid GroupType ID required', 400);
         }
         try {
            $type = GroupType::get((int)$resourceId);
            echo json_encode($type);
         } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 404);
         }
         break;

      case 'GET all':
         Auth::checkPermission($token, 'view_group_types');
         $page  = max(1, (int)($_GET['page'] ?? 1));
         $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
         try {
            $result = GroupType::getAll($page, $limit);
            echo json_encode($result);
         } catch (Exception $e) {
            Helpers::sendFeedback('Failed to retrieve group types', 400);
         }
         break;

      default:
         Helpers::sendFeedback('GroupType endpoint not found', 404);
   }
   exit;
}

// If we reach here, the route was not recognized
Helpers::sendFeedback('Endpoint not found', 404);