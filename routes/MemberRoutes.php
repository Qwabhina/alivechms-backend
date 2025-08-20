<?php

/**
 * Member API Routes
 * This file handles the routing for member-related operations, including viewing, creating, updating, and deleting members.
 * It checks for authentication and permissions before processing requests.
 * It uses the Member model for database interactions and returns JSON responses.
 * Requires authentication via a Bearer token and appropriate permissions.
 */
require_once __DIR__ . '/../core/Member.php';

if (!$token || !Auth::verify($token)) Helpers::sendFeedback('Unauthorized', 401);

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
    case 'GET member/recent':
        Auth::checkPermission($token, 'view_members');

        $orm = new ORM();
        $members = $orm->selectWithJoin(
            baseTable: 'churchmember c',
            joins: [
                ['table' => 'member_phone p', 'on' => 'c.MbrID = p.MbrID', 'type' => 'LEFT OUTER'],
                ['table' => 'userauthentication u', 'on' => 'c.MbrID = u.MbrID', 'type' => 'LEFT OUTER']
            ],
            fields: ['c.*', 'GROUP_CONCAT(p.PhoneNumber) as PhoneNumbers', 'u.Username', 'u.LastLoginAt'],
            conditions: ['c.MbrMembershipStatus' => ':status'],
            params: [':status' => 'Active'],
            orderBy: ['c.MbrRegistrationDate' => 'DESC'],
            groupBy: ['c.MbrID'],
            limit: 10
        );

        echo json_encode($members);

        break;

    case 'GET member/all':
        Auth::checkPermission($token, 'view_members');
        $orm = new ORM();
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
        $offset = ($page - 1) * $limit;

        $members = $orm->selectWithJoin(
            baseTable: 'churchmember c',
            joins: [['table' => 'member_phone p', 'on' => 'c.MbrID = p.MbrID', 'type' => 'LEFT']],
            fields: ['c.*', 'GROUP_CONCAT(p.PhoneNumber) as PhoneNumbers'],
            conditions: ['c.MbrMembershipStatus' => ':status'],
            params: [':status' => 'Active'],
            groupBy: ['c.MbrID'],
            limit: $limit,
            offset: $offset
        );

        $total = $orm->runQuery("SELECT COUNT(*) as total FROM churchmember WHERE MbrMembershipStatus = 'Active'")[0]['total'];

        echo json_encode([
            'data' => $members,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        break;

    case 'POST member/create':
        Auth::checkPermission($token, 'edit_members');

        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $result = Member::register($input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    case 'PUT member/update':
        Auth::checkPermission($token, 'edit_members');

        $input = json_decode(file_get_contents('php://input'), true);
        $mbrId = $pathParts[2] ?? null;

        if (!$mbrId)  Helpers::sendFeedback('Member ID required', 400);

        try {
            $result = Member::update($mbrId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }

        break;

    case "DELETE member/delete":
        Auth::checkPermission($token, 'edit_members');

        $mbrId = $pathParts[2] ?? null;
        if (!$mbrId) Helpers::sendFeedback('Member ID required', 400);

        try {
            $result = Member::delete($mbrId);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 400);
        }
        break;

    case 'GET member/view':
        Auth::checkPermission($token, 'view_members');

        $mbrId = $pathParts[2] ?? null;
        if (!$mbrId) Helpers::sendFeedback('Member ID required', 400);
        if (!is_numeric($mbrId)) Helpers::sendFeedback('Invalid Member ID', 400);

        try {
            $member = Member::get($mbrId);
            echo json_encode($member);
        } catch (Exception $e) {
            Helpers::sendFeedback($e->getMessage(), 404);
        }
        break;

    default:
        Helpers::sendFeedback('Endpoint not found', 404);
}
?>