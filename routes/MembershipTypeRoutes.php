<?php

/**
 * Membership Type API Routes
 * This file handles membership type-related API routes for the AliveChMS backend.
 * It includes routes for creating, updating, deleting, viewing, and listing membership types,
 * as well as assigning types to members, updating assignments, and retrieving member assignments.
 * It uses the MembershipType class for business logic and the Auth class for permission checks.
 */
require_once __DIR__ . '/../core/MembershipType.php';

if (!$token || !Auth::verify($token))  Helpers::sendFeedback('Unauthorized', 401);

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
   case 'POST membershiptype/create':
      // Auth::checkPermission($token, 'manage_membership_types');

      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = MembershipType::create($input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('MembershipType create error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'POST membershiptype/update':
      // Auth::checkPermission($token, 'manage_membership_types');

      $typeId = $pathParts[2] ?? null;
      if (!$typeId) Helpers::sendFeedback('Membership type ID required', 400);

      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = MembershipType::update($typeId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('MembershipType update error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'POST membershiptype/delete':
      // Auth::checkPermission($token, 'manage_membership_types');

      $typeId = $pathParts[2] ?? null;
      if (!$typeId) Helpers::sendFeedback('Membership type ID required', 400);

      try {
         $result = MembershipType::delete($typeId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('MembershipType delete error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'GET membershiptype/view':
      // Auth::checkPermission($token, 'view_membership_types');

      $typeId = $pathParts[2] ?? null;
      if (!$typeId) Helpers::sendFeedback('Membership type ID required', 400);

      try {
         $type = MembershipType::get($typeId);
         echo json_encode($type);
      } catch (Exception $e) {
         Helpers::logError('MembershipType get error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 404);
      }
      break;

   case 'GET membershiptype/all':
      // Auth::checkPermission($token, 'view_membership_types');

      $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
      $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
      $filters = [];

      if (isset($_GET['name'])) $filters['name'] = $_GET['name'];

      try {
         $result = MembershipType::getAll($page, $limit, $filters);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('MembershipType getAll error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'POST membershiptype/assign':
      // Auth::checkPermission($token, 'manage_membership_types');

      $memberId = $pathParts[2] ?? null;
      if (!$memberId) Helpers::sendFeedback('Member ID required', 400);

      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = MembershipType::assign($memberId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('MembershipType assign error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'POST membershiptype/updateAssignment':
      // Auth::checkPermission($token, 'manage_membership_types');

      $assignmentId = $pathParts[2] ?? null;
      if (!$assignmentId) Helpers::sendFeedback('Assignment ID required', 400);

      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = MembershipType::updateAssignment($assignmentId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('MembershipType updateAssignment error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   case 'GET membershiptype/memberassignments':
      // Auth::checkPermission($token, 'view_membership_types');

      $memberId = $pathParts[2] ?? null;
      if (!$memberId) Helpers::sendFeedback('Member ID required', 400);

      $filters = [];
      if (isset($_GET['active']) && $_GET['active'] === 'true') $filters['active'] = true;
      if (isset($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
      if (isset($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];

      try {
         $result = MembershipType::getMemberAssignments($memberId, $filters);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::logError('MembershipType getMemberAssignments error: ' . $e->getMessage());
         Helpers::sendFeedback($e->getMessage(), 400);
      }
      break;

   default:
      Helpers::sendFeedback('Request Malformed', 405);
      break;
}