<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Family.php';
require_once __DIR__ . '/../core/Helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$action = isset($pathParts[1]) ? $pathParts[1] : '';
$param = isset($pathParts[2]) ? $pathParts[2] : null;
$token = isset($_SERVER['HTTP_AUTHORIZATION']) ? str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']) : '';

try {
    switch ("$method $action") {
        case 'POST family':
            Auth::checkPermission($token, 'manage_families');
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(Family::create($data));
            break;

        case 'PUT family':
            Auth::checkPermission($token, 'manage_families');
            if (!$param) {
                throw new Exception('Family ID required');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(Family::update($param, $data));
            break;

        case 'DELETE family':
            Auth::checkPermission($token, 'manage_families');
            if (!$param) {
                throw new Exception('Family ID required');
            }
            echo json_encode(Family::delete($param));
            break;

        case 'GET family':
            Auth::checkPermission($token, 'view_families');
            if (!$param) {
                throw new Exception('Family ID required');
            }
            echo json_encode(Family::get($param));
            break;

        case 'GET families':
            Auth::checkPermission($token, 'view_families');
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $filters = [];
            if (isset($_GET['branch_id'])) {
                $filters['branch_id'] = $_GET['branch_id'];
            }
            if (isset($_GET['name'])) {
                $filters['name'] = $_GET['name'];
            }
            echo json_encode(Family::getAll($page, $limit, $filters));
            break;

        case 'POST family/members':
            Auth::checkPermission($token, 'manage_families');
            if (!$param) {
                throw new Exception('Family ID required');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(Family::addMember($param, $data));
            break;

        case 'DELETE family/members':
            Auth::checkPermission($token, 'manage_families');
            if (!$param || !isset($pathParts[4])) {
                throw new Exception('Family ID and Member ID required');
            }
            echo json_encode(Family::removeMember($param, $pathParts[4]));
            break;

        case 'PUT family/members/role':
            Auth::checkPermission($token, 'manage_families');
            if (!$param || !isset($pathParts[4])) {
                throw new Exception('Family ID and Member ID required');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(Family::updateMemberRole($param, $pathParts[4], $data));
            break;

        default:
            throw new Exception('Invalid endpoint or method');
    }
} catch (Exception $e) {
    Helpers::sendError($e->getMessage(), 400);
}
?>