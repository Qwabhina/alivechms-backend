<?php

/**
 * Member API Routes
 *
 * Handles all member-related endpoints:
 * - Registration
 * - Profile update
 * - Soft delete
 * - Retrieve single member
 * - Paginated list of active members
 * - Recent members (for dashboard)
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-20
 */

require_once __DIR__ . '/../core/Member.php';

// All member routes require authentication except registration
if ($section === 'member' && $pathParts[1] !== 'create') {
    if (!$token || !Auth::verify($token)) {
        Helpers::sendFeedback('Unauthorized: Valid token required', 401);
    }
}

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {

    // Public registration endpoint
    case 'POST member/create':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
        }
        try {
            $result = Member::register($input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    // Protected routes below
    case 'PUT member/update':
        Auth::checkPermission($token, 'edit_members');
        $mbrId = $pathParts[2] ?? null;
        if (!$mbrId || !is_numeric($mbrId)) {
            Helpers::sendFeedback('Valid Member ID required', 400);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
        }
        try {
            $result = Member::update((int)$mbrId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    case 'DELETE member/delete':
        // Auth::checkPermission($token, 'edit_members');
        $mbrId = $pathParts[2] ?? null;
        if (!$mbrId || !is_numeric($mbrId)) {
            Helpers::sendFeedback('Valid Member ID required', 400);
        }
        try {
            $result = Member::delete((int)$mbrId);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    case 'GET member/view':
        Auth::checkPermission($token, 'view_members');
        $mbrId = $pathParts[2] ?? null;
        if (!$mbrId || !is_numeric($mbrId)) {
            Helpers::sendFeedback('Valid Member ID required', 400);
        }
        try {
            $member = Member::get((int)$mbrId);
            echo json_encode($member);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 404);
        }
        break;

    case 'GET member/all':
        Auth::checkPermission($token, 'view_members');
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
        try {
            $result = Member::getAll($page, $limit);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::logError('Error fetching members: ' . $e->getMessage());
            Helpers::sendFeedback('Failed to retrieve members', 400);
        }
        break;

    case 'GET member/recent':
        Auth::checkPermission($token, 'view_members');
        try {
            $orm = new ORM();
            $members = $orm->selectWithJoin(
                baseTable: 'churchmember c',
                joins: [
                    ['table' => 'member_phone p', 'on' => 'c.MbrID = p.MbrID', 'type' => 'LEFT'],
                    ['table' => 'userauthentication u', 'on' => 'c.MbrID = u.MbrID', 'type' => 'LEFT']
                ],
                fields: [
                    'c.MbrID',
                    'c.MbrFirstName',
                    'c.MbrFamilyName',
                    'c.MbrEmailAddress',
                    'c.MbrRegistrationDate',
                    'GROUP_CONCAT(DISTINCT p.PhoneNumber) AS PhoneNumbers',
                    'u.Username',
                    'u.LastLoginAt'
                ],
                conditions: ['c.MbrMembershipStatus' => ':status', 'c.Deleted' => 0],
                params: [':status' => 'Active'],
                groupBy: ['c.MbrID'],
                orderBy: ['c.MbrRegistrationDate' => 'DESC'],
                limit: 10
            );
            echo json_encode(['data' => $members]);
        } catch (Exception $e) {
            Helpers::sendFeedback('Failed to retrieve recent members', 400);
        }
        break;

    default:
        Helpers::sendFeedback('Endpoint not found', 404);
}