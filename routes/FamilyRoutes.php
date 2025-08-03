<?php

/**
 * Family API Routes
 * This file handles the routing for family management, including creation, updating, deletion, and retrieval.
 * It checks for authentication and permissions before processing requests.
 * It uses the Family model for database interactions and returns JSON responses.
 * Requires authentication via a Bearer token and appropriate permissions.
 */
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
    Helpers::sendFeedback($e->getMessage(), 400);
}
?>