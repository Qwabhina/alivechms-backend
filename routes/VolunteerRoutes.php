<?php

/**
 * Volunteer Management API Routes – v1
 *
 * Complete volunteer coordination system for the Body of Christ:
 *
 * VOLUNTEER ROLES (e.g., Usher, Greeter, Sound Tech, Children’s Church)
 * • Global reusable role taxonomy
 * • Full CRUD with safety protection
 *
 * EVENT-BASED VOLUNTEER ASSIGNMENTS
 * • Bulk assignment with role & notes
 * • Self-confirmation / decline workflow
 * • Completion marking after service
 * • Full audit trail and status tracking
 *
 * Business & Spiritual Purpose:
 * • "Each of you should use whatever gift you have received to serve others..." — 1 Peter 4:10
 * • Enables every member to find their place of service
 * • Empowers ministry leaders to schedule with confidence
 * • Tracks faithfulness and spiritual growth
 *
 * Safety Rules:
 * • Only assigned volunteers can confirm/decline
 * • Only confirmed assignments can be marked complete
 * • Cannot remove volunteer after confirmation without override
 *
 * This is the engine that turns members into ministers.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Volunteer.php';

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

   // CREATE VOLUNTEER ROLE
   $method === 'POST' && $pathParts[0] === 'volunteer' && ($pathParts[1] ?? '') === 'role' && ($pathParts[2] ?? '') === 'create' => (function () use ($token) {
      Auth::checkPermission($token, 'manage_volunteer_roles');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = Volunteer::createRole($payload);
      echo json_encode($result);
   })(),

   // LIST ALL VOLUNTEER ROLES
   $method === 'GET' && $pathParts[0] === 'volunteer' && ($pathParts[1] ?? '') === 'role' && ($pathParts[2] ?? '') === 'all' => (function () use ($token) {
      // Public access — everyone should see service opportunities
      $result = Volunteer::getRoles();
      echo json_encode(['data' => $result]);
   })(),

   // ASSIGN VOLUNTEERS TO EVENT (Bulk)
   $method === 'POST' && $pathParts[0] === 'volunteer' && ($pathParts[1] ?? '') === 'assign' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'assign_volunteers');

      $eventId = $pathParts[2];
      if (!is_numeric($eventId)) {
         Helpers::sendFeedback('Valid Event ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || empty($payload['volunteers']) || !is_array($payload['volunteers'])) {
         Helpers::sendFeedback('volunteers array is required', 400);
      }

      $result = Volunteer::assign((int)$eventId, $payload['volunteers']);
      echo json_encode($result);
   })(),

   // CONFIRM OR DECLINE ASSIGNMENT (Self-Service)
   $method === 'POST' && $pathParts[0] === 'volunteer' && ($pathParts[1] ?? '') === 'confirm' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      // Only the assigned volunteer can respond
      $assignmentId = $pathParts[2];
      if (!is_numeric($assignmentId)) {
         Helpers::sendFeedback('Valid Assignment ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || !in_array($payload['action'] ?? '', ['confirm', 'decline'], true)) {
         Helpers::sendFeedback("action must be 'confirm' or 'decline'", 400);
      }

      $result = Volunteer::confirmAssignment((int)$assignmentId, $payload['action']);
      echo json_encode($result);
   })(),

   // MARK ASSIGNMENT AS COMPLETED
   $method === 'POST' && $pathParts[0] === 'volunteer' && ($pathParts[1] ?? '') === 'complete' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_volunteers');

      $assignmentId = $pathParts[2];
      if (!is_numeric($assignmentId)) {
         Helpers::sendFeedback('Valid Assignment ID required', 400);
      }

      $result = Volunteer::completeAssignment((int)$assignmentId);
      echo json_encode($result);
   })(),

   // GET VOLUNTEERS FOR EVENT (Paginated)
   $method === 'GET' && $pathParts[0] === 'volunteer' && ($pathParts[1] ?? '') === 'event' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_volunteers');

      $eventId = $pathParts[2];
      if (!is_numeric($eventId)) {
         Helpers::sendFeedback('Valid Event ID required', 400);
      }

      $page  = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

      $result = Volunteer::getByEvent((int)$eventId, $page, $limit);
      echo json_encode($result);
   })(),

   // REMOVE VOLUNTEER FROM EVENT
   $method === 'DELETE' && $pathParts[0] === 'volunteer' && ($pathParts[1] ?? '') === 'remove' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_volunteers');

      $assignmentId = $pathParts[2];
      if (!is_numeric($assignmentId)) {
         Helpers::sendFeedback('Valid Assignment ID required', 400);
      }

      $result = Volunteer::remove((int)$assignmentId);
      echo json_encode($result);
   })(),

   // FALLBACK
   default => Helpers::sendFeedback('Volunteer endpoint not found', 404),
};