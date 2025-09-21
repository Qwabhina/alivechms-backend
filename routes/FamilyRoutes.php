<?php

/**
 * Family API Routes
 * This file handles family-related API routes for the AliveChMS backend.
 * It includes routes for creating, updating, deleting, viewing, and listing families,
 * as well as managing family members (adding, removing, and updating roles).
 * It uses the Family class for business logic and the Auth class for permission checks.
 */
require_once __DIR__ . '/../core/Family.php';

if (!$token || !Auth::verify($token))  Helpers::sendFeedback('Unauthorized', 401);

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
    case 'POST family/create':
        // Auth::checkPermission($token, 'manage_families');

        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $result = Family::create($input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::logError('Family create error: ' . $e->getMessage());
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    case 'POST family/update':
        // Auth::checkPermission($token, 'manage_families');

        $familyId = $pathParts[2] ?? null;
        if (!$familyId) Helpers::sendFeedback('Family ID required', 400);

        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $result = Family::update($familyId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::logError('Family update error: ' . $e->getMessage());
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    case 'POST family/delete':
        // Auth::checkPermission($token, 'manage_families');

        $familyId = $pathParts[2] ?? null;
        if (!$familyId) Helpers::sendFeedback('Family ID required', 400);

        try {
            $result = Family::delete($familyId);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::logError('Family delete error: ' . $e->getMessage());
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    case 'GET family/view':
        // Auth::checkPermission($token, 'view_families');

        $familyId = $pathParts[2] ?? null;
        if (!$familyId) Helpers::sendFeedback('Family ID required', 400);

        try {
            $family = Family::get($familyId);
            echo json_encode($family);
        } catch (Exception $e) {
            Helpers::logError('Family get error: ' . $e->getMessage());
            Helpers::sendFeedback($e->getMessage(), 404);
        }
        break;

    case 'GET family/all':
        // Auth::checkPermission($token, 'view_families');

        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
        $filters = [];

        if (isset($_GET['branch_id'])) $filters['branch_id'] = $_GET['branch_id'];
        if (isset($_GET['name'])) $filters['name'] = $_GET['name'];

        try {
            $result = Family::getAll($page, $limit, $filters);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::logError('Family getAll error: ' . $e->getMessage());
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    case 'POST family/addMember':
        // Auth::checkPermission($token, 'manage_families');

        $familyId = $pathParts[2] ?? null;
        if (!$familyId) Helpers::sendFeedback('Family ID required', 400);

        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $result = Family::addMember($familyId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::logError('Family addMember error: ' . $e->getMessage());
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    case 'POST family/removeMember':
        // Auth::checkPermission($token, 'manage_families');

        $familyId = $pathParts[2] ?? null;
        $memberId = $pathParts[3] ?? null;
        if (!$familyId || !$memberId) Helpers::sendFeedback('Family ID and Member ID required', 400);

        try {
            $result = Family::removeMember($familyId, $memberId);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::logError('Family removeMember error: ' . $e->getMessage());
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    case 'POST family/members/role':
        // Auth::checkPermission($token, 'manage_families');

        $familyId = $pathParts[2] ?? null;
        $memberId = $pathParts[4] ?? null;
        if (!$familyId || !$memberId) Helpers::sendFeedback('Family ID and Member ID required', 400);

        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $result = Family::updateMemberRole($familyId, $memberId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::logError('Family updateMemberRole error: ' . $e->getMessage());
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    default:
        Helpers::sendFeedback('Request Malformed', 405);
        break;
}