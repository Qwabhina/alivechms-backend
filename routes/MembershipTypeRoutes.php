<?php

/**
 * Membership Type & Assignment API Routes – v1
 *
 * Complete church membership tier system with lifecycle management:
 *
 * MEMBERSHIP TYPES (e.g., Full Member, Associate, New Convert, Child)
 * • Full CRUD with uniqueness enforcement
 * • Deletion protection when assigned to members
 * • Clean taxonomy for reporting and permissions
 *
 * MEMBER ASSIGNMENTS
 * • One active membership per member at a time (strict rule)
 * • Start/end dates with overlap prevention
 * • Historical tracking for life-stage analysis
 * • Bulk and single retrieval with status filtering
 *
 * Business Rules Enforced:
 * • Only one active membership assignment per member
 * • Cannot assign overlapping date ranges
 * • Cannot delete a type currently in use
 * • End date must be after start date
 *
 * Essential for:
 * • Membership ceremonies & reporting
 * • Voting rights and leadership eligibility
 * • Spiritual growth tracking
 * • Annual church census
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/MembershipType.php';

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

   // CREATE MEMBERSHIP TYPE
   $method === 'POST' && $path === 'membershiptype/create' => (function () use ($token) {
      Auth::checkPermission($token, 'manage_membership_types');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = MembershipType::create($payload);
      echo json_encode($result);
   })(),

   // UPDATE MEMBERSHIP TYPE
   $method === 'POST' && $pathParts[0] === 'membershiptype' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_membership_types');

      $typeId = $pathParts[2];
      if (!is_numeric($typeId)) {
         Helpers::sendFeedback('Valid Membership Type ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = MembershipType::update((int)$typeId, $payload);
      echo json_encode($result);
   })(),

   // DELETE MEMBERSHIP TYPE
   $method === 'POST' && $pathParts[0] === 'membershiptype' && ($pathParts[1] ?? '') === 'delete' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_membership_types');

      $typeId = $pathParts[2];
      if (!is_numeric($typeId)) {
         Helpers::sendFeedback('Valid Membership Type ID required', 400);
      }

      $result = MembershipType::delete((int)$typeId);
      echo json_encode($result);
   })(),

   // VIEW SINGLE MEMBERSHIP TYPE
   $method === 'GET' && $pathParts[0] === 'membershiptype' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_membership_types');

      $typeId = $pathParts[2];
      if (!is_numeric($typeId)) {
         Helpers::sendFeedback('Valid Membership Type ID required', 400);
      }

      $type = MembershipType::get((int)$typeId);
      echo json_encode($type);
   })(),

   // LIST ALL MEMBERSHIP TYPES (Paginated + Search)
   $method === 'GET' && $path === 'membershiptype/all' => (function () use ($token) {
      Auth::checkPermission($token, 'view_membership_types');

      $page  = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

      $filters = [];
      if (!empty($_GET['name'])) {
         $filters['name'] = trim($_GET['name']);
      }

      $result = MembershipType::getAll($page, $limit, $filters);
      echo json_encode($result);
   })(),

   // ASSIGN MEMBERSHIP TYPE TO MEMBER
   $method === 'POST' && $pathParts[0] === 'membershiptype' && ($pathParts[1] ?? '') === 'assign' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_membership_types');

      $memberId = $pathParts[2];
      if (!is_numeric($memberId)) {
         Helpers::sendFeedback('Valid Member ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = MembershipType::assign((int)$memberId, $payload);
      echo json_encode($result);
   })(),

   // UPDATE MEMBERSHIP ASSIGNMENT (e.g., set end date)
   $method === 'POST' && $pathParts[0] === 'membershiptype' && ($pathParts[1] ?? '') === 'updateAssignment' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_membership_types');

      $assignmentId = $pathParts[2];
      if (!is_numeric($assignmentId)) {
         Helpers::sendFeedback('Valid Assignment ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = MembershipType::updateAssignment((int)$assignmentId, $payload);
      echo json_encode($result);
   })(),

   // GET MEMBER'S MEMBERSHIP HISTORY
   $method === 'GET' && $pathParts[0] === 'membershiptype' && ($pathParts[1] ?? '') === 'memberassignments' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_membership_types');

      $memberId = $pathParts[2];
      if (!is_numeric($memberId)) {
         Helpers::sendFeedback('Valid Member ID required', 400);
      }

      $filters = [];
      if (isset($_GET['active']) && $_GET['active'] === 'true') $filters['active'] = true;
      if (!empty($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
      if (!empty($_GET['end_date']))   $filters['end_date']   = $_GET['end_date'];

      $result = MembershipType::getMemberAssignments((int)$memberId, $filters);
      echo json_encode($result);
   })(),

   // FALLBACK
   default => Helpers::sendFeedback('MembershipType endpoint not found', 404),
};