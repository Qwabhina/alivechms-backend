<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Family.php';
require_once __DIR__ . '/../core/Helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$token = Auth::getBearerToken();

if (!$token || !Auth::verify($token)) {
    Helpers::sendError('Unauthorized', 401);
}

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
    case 'POST family/create':
        Auth::checkPermission($token, 'edit_members');
        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $result = Family::create($input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    case 'GET family/view':
        Auth::checkPermission($token, 'view_members');
        $familyId = $pathParts[2] ?? null;
        if (!$familyId) {
            Helpers::sendError('Family ID required', 400);
        }
        try {
            $family = Family::get($familyId);
            echo json_encode($family);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 404);
        }
        break;

    case 'PUT family/update':
        Auth::checkPermission($token, 'edit_members');
        $familyId = $pathParts[2] ?? null;
        if (!$familyId) {
            Helpers::sendError('Family ID required', 400);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $result = Family::update($familyId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    case 'DELETE family/delete':
        Auth::checkPermission($token, 'edit_members');
        $familyId = $pathParts[2] ?? null;
        if (!$familyId) {
            Helpers::sendError('Family ID required', 400);
        }
        try {
            $result = Family::delete($familyId);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    default:
        Helpers::sendError('Endpoint not found', 404);
}
