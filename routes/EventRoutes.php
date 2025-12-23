<?php

/**
 * Event API Routes – v1
 *
 * Complete church event management:
 * - Create, update, delete events
 * - Bulk & single attendance recording
 * - View single event with attendance summary
 * - Paginated listing with date/branch filtering
 *
 * All operations strictly permission-controlled.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Event.php';

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

   // CREATE EVENT
   $method === 'POST' && $path === 'event/create' => (function () use ($token) {
      Auth::checkPermission($token, 'manage_events');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = Event::create($payload);
      echo json_encode($result);
   })(),

   // UPDATE EVENT
   $method === 'PUT' && $pathParts[0] === 'event' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_events');

      $eventId = $pathParts[2];
      if (!is_numeric($eventId)) {
         Helpers::sendFeedback('Valid Event ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = Event::update((int)$eventId, $payload);
      echo json_encode($result);
   })(),

   // DELETE EVENT
   $method === 'DELETE' && $pathParts[0] === 'event' && ($pathParts[1] ?? '') === 'delete' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'manage_events');

      $eventId = $pathParts[2];
      if (!is_numeric($eventId)) {
         Helpers::sendFeedback('Valid Event ID required', 400);
      }

      $result = Event::delete((int)$eventId);
      echo json_encode($result);
   })(),

   // VIEW SINGLE EVENT
   $method === 'GET' && $pathParts[0] === 'event' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_events');

      $eventId = $pathParts[2];
      if (!is_numeric($eventId)) {
         Helpers::sendFeedback('Valid Event ID required', 400);
      }

      $event = Event::get((int)$eventId);
      echo json_encode($event);
   })(),

   // LIST ALL EVENTS (Paginated + Filtered)
   $method === 'GET' && $path === 'event/all' => (function () use ($token) {
      Auth::checkPermission($token, 'view_events');

      $page  = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

      $filters = [];
      foreach (['branch_id', 'start_date', 'end_date'] as $key) {
         if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $filters[$key] = $_GET[$key];
         }
      }

      $result = Event::getAll($page, $limit, $filters);
      echo json_encode($result);
   })(),

   // RECORD BULK ATTENDANCE
   $method === 'POST' && $pathParts[0] === 'event' && ($pathParts[1] ?? '') === 'attendance' && ($pathParts[2] ?? '') === 'bulk' && isset($pathParts[3]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'record_attendance');

      $eventId = $pathParts[3];
      if (!is_numeric($eventId)) {
         Helpers::sendFeedback('Valid Event ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || empty($payload['attendances']) || !is_array($payload['attendances'])) {
         Helpers::sendFeedback('attendances array is required', 400);
      }

      $result = Event::recordBulkAttendance((int)$eventId, $payload);
      echo json_encode($result);
   })(),

   // RECORD SINGLE ATTENDANCE (Mobile/Self-Check-in)
   $method === 'POST' && $pathParts[0] === 'event' && ($pathParts[1] ?? '') === 'attendance' && ($pathParts[2] ?? '') === 'single' && isset($pathParts[3]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'record_attendance');

      $eventId = $pathParts[3];
      if (!is_numeric($eventId)) {
         Helpers::sendFeedback('Valid Event ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload) || empty($payload['member_id'])) {
         Helpers::sendFeedback('member_id is required', 400);
      }

      $status = $payload['status'] ?? 'Present';
      $result = Event::recordSingleAttendance((int)$eventId, (int)$payload['member_id'], $status);
      echo json_encode($result);
   })(),

   // FALLBACK
   default => Helpers::sendFeedback('Event endpoint not found', 404),
};