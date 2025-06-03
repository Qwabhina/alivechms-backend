<?php

/**
 * Group API Routes
 * This file handles the routing for group management, including creation, updating, deletion, and retrieval.
 * It checks for authentication and permissions before processing requests.
 * It uses the Group and GroupType models for database interactions and returns JSON responses.
 * Requires authentication via a Bearer token and appropriate permissions. 
 */
require_once __DIR__ . '/Group.php';
require_once __DIR__ . '/GroupType.php';
$action = isset($pathParts[1]) ? $pathParts[1] : '';
$param = isset($pathParts[2]) ? $pathParts[2] : null;

try {
   switch ("$method $action") {
      case 'POST group':
         Auth::checkPermission($token, 'manage_groups');
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(Group::create($data));
         break;

      case 'PUT group':
         Auth::checkPermission($token, 'manage_groups');
         if (!$param) {
            throw new Exception('Group ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(Group::update($param, $data));
         break;

      case 'DELETE group':
         Auth::checkPermission($token, 'manage_groups');
         if (!$param) {
            throw new Exception('Group ID required');
         }
         echo json_encode(Group::delete($param));
         break;

      case 'GET group':
         Auth::checkPermission($token, 'view_groups');
         if (!$param) {
            throw new Exception('Group ID required');
         }
         echo json_encode(Group::get($param));
         break;

      case 'GET groups':
         Auth::checkPermission($token, 'view_groups');
         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
         $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
         $filters = [];
         if (isset($_GET['type_id'])) {
            $filters['type_id'] = $_GET['type_id'];
         }
         if (isset($_GET['branch_id'])) {
            $filters['branch_id'] = $_GET['branch_id'];
         }
         if (isset($_GET['name'])) {
            $filters['name'] = $_GET['name'];
         }
         echo json_encode(Group::getAll($page, $limit, $filters));
         break;

      case 'POST group/members':
         Auth::checkPermission($token, 'manage_groups');
         if (!$param) {
            throw new Exception('Group ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         if (!isset($data['member_id'])) {
            throw new Exception('Member ID required');
         }
         echo json_encode(Group::addMember($param, $data['member_id']));
         break;

      case 'DELETE group/members':
         Auth::checkPermission($token, 'manage_groups');
         if (!$param || !isset($pathParts[4])) {
            throw new Exception('Group ID and Member ID required');
         }
         echo json_encode(Group::removeMember($param, $pathParts[4]));
         break;

      case 'GET group/members':
         Auth::checkPermission($token, 'view_groups');
         if (!$param) {
            throw new Exception('Group ID required');
         }
         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
         $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
         echo json_encode(Group::getMembers($param, $page, $limit));
         break;

      case 'POST grouptype':
         Auth::checkPermission($token, 'manage_group_types');
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(GroupType::create($data));
         break;

      case 'PUT grouptype':
         Auth::checkPermission($token, 'manage_group_types');
         if (!$param) {
            throw new Exception('Group Type ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(GroupType::update($param, $data));
         break;

      case 'DELETE grouptype':
         Auth::checkPermission($token, 'manage_group_types');
         if (!$param) {
            throw new Exception('Group Type ID required');
         }
         echo json_encode(GroupType::delete($param));
         break;

      case 'GET grouptype':
         Auth::checkPermission($token, 'view_group_types');
         if (!$param) {
            throw new Exception('Group Type ID required');
         }
         echo json_encode(GroupType::get($param));
         break;

      case 'GET grouptypes':
         Auth::checkPermission($token, 'view_group_types');
         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
         $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
         echo json_encode(GroupType::getAll($page, $limit));
         break;

      case 'POST group/messages':
         Auth::checkPermission($token, 'send_communication');
         if (!$param) {
            throw new Exception('Group ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(Group::sendMessage($param, $data));
         break;

      case 'GET group/messages':
         Auth::checkPermission($token, 'view_communication');
         if (!$param) {
            throw new Exception('Group ID required');
         }
         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
         $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
         echo json_encode(Group::getMessages($param, $page, $limit));
         break;

      default:
         Helpers::sendError('Endpoint not found', 404);
   }
} catch (Exception $e) {
   Helpers::sendError($e->getMessage(), 400);
}
