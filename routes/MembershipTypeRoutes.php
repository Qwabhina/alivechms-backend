<?php

/**
 * Membership Type API Routes
 * This file handles the routing for membership type management, including creation, updating, deletion, and retrieval.
 * It checks for authentication and permissions before processing requests.
 * It uses the MembershipType model for database interactions and returns JSON responses.
 * Requires authentication via a Bearer token and appropriate permissions.
 */
require_once __DIR__ . '/MembershipType.php';
$action = isset($pathParts[1]) ? $pathParts[1] : '';
$param = isset($pathParts[2]) ? $pathParts[2] : null;

try {
   switch ("$method $action") {
      case 'POST membership-type':
         Auth::checkPermission($token, 'manage_membership_types');
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(MembershipType::createType($data));
         break;

      case 'PUT membership-type':
         Auth::checkPermission($token, 'manage_membership_types');
         if (!$param) {
            throw new Exception('Membership type ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(MembershipType::updateType($param, $data));
         break;

      case 'DELETE membership-type':
         Auth::checkPermission($token, 'manage_membership_types');
         if (!$param) {
            throw new Exception('Membership type ID required');
         }
         echo json_encode(MembershipType::deleteType($param));
         break;

      case 'GET membership-type':
         Auth::checkPermission($token, 'view_membership_types');
         if (!$param) {
            throw new Exception('Membership type ID required');
         }
         echo json_encode(MembershipType::getType($param));
         break;

      case 'GET membership-types':
         Auth::checkPermission($token, 'view_membership_types');
         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
         $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
         $filters = [];
         if (isset($_GET['name'])) {
            $filters['name'] = $_GET['name'];
         }
         echo json_encode(MembershipType::getAllTypes($page, $limit, $filters));
         break;

      case 'POST member/membership-type':
         Auth::checkPermission($token, 'manage_membership_types');
         if (!$param) {
            throw new Exception('Member ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(MembershipType::assignType($param, $data));
         break;

      case 'PUT membership-assignment':
         Auth::checkPermission($token, 'manage_membership_types');
         if (!$param) {
            throw new Exception('Assignment ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(MembershipType::updateAssignment($param, $data));
         break;

      case 'GET member/membership-types':
         Auth::checkPermission($token, 'view_membership_types');
         if (!$param) {
            throw new Exception('Member ID required');
         }
         $filters = [];
         if (isset($_GET['active']) && $_GET['active'] === 'true') {
            $filters['active'] = true;
         }
         if (isset($_GET['start_date'])) {
            $filters['start_date'] = $_GET['start_date'];
         }
         if (isset($_GET['end_date'])) {
            $filters['end_date'] = $_GET['end_date'];
         }
         echo json_encode(MembershipType::getMemberAssignments($param, $filters));
         break;

      default:
         Helpers::sendError('Endpoint not found', 404);
         break;
   }
} catch (Exception $e) {
   Helpers::sendError($e->getMessage(), 400);
}
