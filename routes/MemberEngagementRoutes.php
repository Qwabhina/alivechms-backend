<?php
require_once __DIR__ . '/MemberEngagement.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$action = isset($pathParts[1]) ? $pathParts[1] : '';
$param = isset($pathParts[2]) ? $pathParts[2] : null;
$token = isset($_SERVER['HTTP_AUTHORIZATION']) ? str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']) : '';

try {
   switch ("$method $action") {
      case 'POST engagement/attendance':
         Auth::checkPermission($token, 'manage_engagement');
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(MemberEngagement::recordAttendance($data));
         break;

      case 'GET member/engagement':
         Auth::checkPermission($token, 'view_engagement');
         if (!$param) {
            throw new Exception('Member ID required');
         }
         $filters = [];
         if (isset($_GET['start_date'])) {
            $filters['start_date'] = $_GET['start_date'];
         }
         if (isset($_GET['end_date'])) {
            $filters['end_date'] = $_GET['end_date'];
         }
         if (isset($_GET['branch_id'])) {
            $filters['branch_id'] = $_GET['branch_id'];
         }
         echo json_encode(MemberEngagement::getEngagementReport($param, $filters));
         break;

      case 'POST member/phone':
         Auth::checkPermission($token, 'manage_engagement');
         if (!$param) {
            throw new Exception('Member ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(MemberEngagement::addPhone($param, $data));
         break;

      case 'PUT phone':
         Auth::checkPermission($token, 'manage_engagement');
         if (!$param) {
            throw new Exception('Phone ID required');
         }
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(MemberEngagement::updatePhone($param, $data));
         break;

      case 'DELETE phone':
         Auth::checkPermission($token, 'manage_engagement');
         if (!$param) {
            throw new Exception('Phone ID required');
         }
         echo json_encode(MemberEngagement::deletePhone($param));
         break;

      case 'GET member/phones':
         Auth::checkPermission($token, 'view_engagement');
         if (!$param) {
            throw new Exception('Member ID required');
         }
         echo json_encode(MemberEngagement::getPhones($param));
         break;

      case 'POST engagement/message':
         Auth::checkPermission($token, 'send_communication');
         $data = json_decode(file_get_contents('php://input'), true);
         echo json_encode(MemberEngagement::sendEngagementMessage($data));
         break;

      default:
         throw new Exception('Invalid endpoint or method');
   }
} catch (Exception $e) {
   Helpers::sendError($e->getMessage(), 400);
}
