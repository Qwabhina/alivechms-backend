<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Event.php';
require_once __DIR__ . '/../core/Helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$token = Auth::getBearerToken();
$pathParts = explode('/', trim($path, '/'));

if (!$token || !Auth::verify($token)) {
   Helpers::sendError('Unauthorized', 401);
}

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
   case 'POST event/create':
      Auth::checkPermission($token, 'create_event');
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $decoded = Auth::verify($token);
         $input['created_by'] = $decoded['user_id'];
         $result = Event::create($input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'PUT event/update':
      Auth::checkPermission($token, 'create_event');
      $eventId = $pathParts[2] ?? null;
      if (!$eventId) {
         Helpers::sendError('Event ID required', 400);
      }
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $decoded = Auth::verify($token);
         $input['created_by'] = $decoded['user_id'];
         $result = Event::update($eventId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'DELETE event/delete':
      Auth::checkPermission($token, 'delete_event');
      $eventId = $pathParts[2] ?? null;
      if (!$eventId) {
         Helpers::sendError('Event ID required', 400);
      }
      try {
         $result = Event::delete($eventId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'GET event/view':
      Auth::checkPermission($token, 'view_event');
      $eventId = $pathParts[2] ?? null;
      if (!$eventId) {
         Helpers::sendError('Event ID required', 400);
      }
      try {
         $event = Event::get($eventId);
         echo json_encode($event);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 404);
      }
      break;

   case 'GET event/all':
      Auth::checkPermission($token, 'view_event');
      $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
      $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
      $filters = [];
      if (isset($_GET['branch_id'])) {
         $filters['branch_id'] = $_GET['branch_id'];
      }
      if (isset($_GET['date_from'])) {
         $filters['date_from'] = $_GET['date_from'];
      }
      if (isset($_GET['date_to'])) {
         $filters['date_to'] = $_GET['date_to'];
      }
      try {
         $result = Event::getAll($page, $limit, $filters);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'POST event/attendance':
      Auth::checkPermission($token, 'manage_attendance');
      $eventId = $pathParts[2] ?? null;
      if (!$eventId) {
         Helpers::sendError('Event ID required', 400);
      }
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = Event::recordAttendance($eventId, $input['member_id'], $input['status']);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'POST event/volunteer':
      Auth::checkPermission($token, 'manage_volunteers');
      $eventId = $pathParts[2] ?? null;
      if (!$eventId) {
         Helpers::sendError('Event ID required', 400);
      }
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = Event::assignVolunteer($eventId, $input['member_id'], $input['role']);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'GET event/attendance':
      Auth::checkPermission($token, 'view_event');
      $eventId = $pathParts[2] ?? null;
      if (!$eventId) {
         Helpers::sendError('Event ID required', 400);
      }
      try {
         $result = Event::getAttendance($eventId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'GET event/volunteers':
      Auth::checkPermission($token, 'view_event');
      $eventId = $pathParts[2] ?? null;
      if (!$eventId) {
         Helpers::sendError('Event ID required', 400);
      }
      try {
         $result = Event::getVolunteers($eventId);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'GET event/report':
      // Auth::checkPermission($token, 'view_event');
      $type = $pathParts[2] ?? null;

      if (!$type) {
         Helpers::sendError('Report type required', 400);
      }
      $filters = [];
      if (isset($_GET['date_from'])) {
         $filters['date_from'] = $_GET['date_from'];
      }
      if (isset($_GET['date_to'])) {
         $filters['date_to'] = $_GET['date_to'];
      }
      if (isset($_GET['event_name'])) {
         $filters['event_name'] = $_GET['event_name'];
      }
      try {
         $result = Event::getReports($type, $filters);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'POST event/bulk-attendance':
      Auth::checkPermission($token, 'manage_attendance');
      $eventId = $pathParts[2] ?? null;
      if (!$eventId) {
         Helpers::sendError('Event ID required', 400);
      }
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $result = Event::bulkAttendance($eventId, $input);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   case 'POST event/self-attendance':
      Auth::checkPermission($token, 'record_own_attendance');
      $eventId = $pathParts[2] ?? null;
      if (!$eventId) {
         Helpers::sendError('Event ID required', 400);
      }
      $input = json_decode(file_get_contents('php://input'), true);
      try {
         $decoded = Auth::verify($token);
         $result = Event::selfAttendance($eventId, $input['status'], $decoded['user_id']);
         echo json_encode($result);
      } catch (Exception $e) {
         Helpers::sendError($e->getMessage(), 400);
      }
      break;

   // DEFAULT BEHAVIOR
   default:
      Helpers::sendError('Endpoint not found', 404);
}
