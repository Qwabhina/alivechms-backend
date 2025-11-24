<?php

/**
 * Family API Routes
 *
 * Handles all family-related endpoints:
 * - Create, update, delete families
 * - Retrieve single family or paginated list
 * - Add/remove members and update member roles
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-20
 */

require_once __DIR__ . '/../core/Family.php';

// All family routes require authentication
if (!$token || !Auth::verify($token)) {
    Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {

    // Create a new family
    case 'POST family/create':
        Auth::checkPermission($token, 'manage_families');
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
        }
        try {
            $result = Family::create($input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    // Update family details
    case 'PUT family/update':
        Auth::checkPermission($token, 'manage_families');
        $familyId = $pathParts[2] ?? null;
        if (!$familyId || !is_numeric($familyId)) {
            Helpers::sendFeedback('Valid Family ID required', 400);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
        }
        try {
            $result = Family::update((int)$familyId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    // Soft delete a family
    case 'DELETE family/delete':
        Auth::checkPermission($token, 'manage_families');
        $familyId = $pathParts[2] ?? null;
        if (!$familyId || !is_numeric($familyId)) {
            Helpers::sendFeedback('Valid Family ID required', 400);
        }
        try {
            $result = Family::delete((int)$familyId);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    // Retrieve a single family with members
    case 'GET family/view':
        Auth::checkPermission($token, 'view_families');
        $familyId = $pathParts[2] ?? null;
        if (!$familyId || !is_numeric($familyId)) {
            Helpers::sendFeedback('Valid Family ID required', 400);
        }
        try {
            $family = Family::get((int)$familyId);
            echo json_encode($family);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 404);
        }
        break;

    // Retrieve paginated list of families
    case 'GET family/all':
        Auth::checkPermission($token, 'view_families');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = max(1, min(100, (int)($_GET['limit'] ?? 10)));
        $filters = [];

        if (!empty($_GET['branch_id']) && is_numeric($_GET['branch_id'])) {
            $filters['branch_id'] = (int)$_GET['branch_id'];
        }
        if (!empty($_GET['name'])) {
            $filters['name'] = trim($_GET['name']);
        }

        try {
            $result = Family::getAll($page, $limit, $filters);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback('Failed to retrieve families', 400);
        }
        break;

    // Add a member to a family
    case 'POST family/addMember':
        Auth::checkPermission($token, 'manage_families');
        $familyId = $pathParts[2] ?? null;
        if (!$familyId || !is_numeric($familyId)) {
            Helpers::sendFeedback('Valid Family ID required', 400);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['member_id']) || empty($input['role'])) {
            Helpers::sendFeedback('member_id and role are required', 400);
        }
        try {
            $result = Family::addMember((int)$familyId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    // Remove a member from a family
    case 'DELETE family/removeMember':
        Auth::checkPermission($token, 'manage_families');
        $familyId = $pathParts[2] ?? null;
        $memberId = $pathParts[3] ?? null;
        if (!$familyId || !is_numeric($familyId) || !$memberId || !is_numeric($memberId)) {
            Helpers::sendFeedback('Valid Family ID and Member ID required', 400);
        }
        try {
            $result = Family::removeMember((int)$familyId, (int)$memberId);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    // Update a member's role in a family
    case 'PUT family/updateMemberRole':
        Auth::checkPermission($token, 'manage_families');
        $familyId = $pathParts[2] ?? null;
        $memberId = $pathParts[3] ?? null;
        if (!$familyId || !is_numeric($familyId) || !$memberId || !is_numeric($memberId)) {
            Helpers::sendFeedback('Valid Family ID and Member ID required', 400);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['role'])) {
            Helpers::sendFeedback('role is required', 400);
        }
        try {
            $result = Family::updateMemberRole((int)$familyId, (int)$memberId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    default:
        Helpers::sendFeedback('Endpoint not found', 404);
}