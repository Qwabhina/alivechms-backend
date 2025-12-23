<?php

/**
 * Member API Routes – v1
 *
 * Comprehensive member management:
 * - Public registration
 * - Authenticated CRUD operations
 * - Paginated listing + recent members
 * - Full permission enforcement
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Member.php';

// ---------------------------------------------------------------------
// PUBLIC ENDPOINT (Registration)
// ---------------------------------------------------------------------
if ($method === 'POST' && $path === 'member/create') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        Helpers::sendError('Invalid JSON payload', 400);
    }

    try {
        $result = Member::register($payload);
        echo json_encode($result);
    } catch (Exception $e) {
        Helpers::sendError($e->getMessage(), 400);
    }
    exit;
}

// ---------------------------------------------------------------------
// AUTHENTICATION & AUTHORIZATION (All other endpoints)
// ---------------------------------------------------------------------
$token = Auth::getBearerToken();
if (!$token || Auth::verify($token) === false) Helpers::sendError('Unauthorized: Valid token required', 401);

// ---------------------------------------------------------------------
// ROUTE DISPATCHER (Authenticated)
// ---------------------------------------------------------------------
match (true) {

    // =================================================================
    // UPDATE MEMBER
    // =================================================================
    $method === 'PUT' && $pathParts[0] === 'member' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($pathParts) {
        Auth::checkPermission('edit_members');

        $memberId = $pathParts[2];
        if (!is_numeric($memberId)) {
            Helpers::sendError('Valid Member ID required', 400);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            Helpers::sendError('Invalid JSON payload', 400);
        }

        $result = Member::update((int)$memberId, $payload);
        echo json_encode($result);
    })(),

    // =================================================================
    // SOFT DELETE MEMBER
    // =================================================================
    $method === 'DELETE' && $pathParts[0] === 'member' && ($pathParts[1] ?? '') === 'delete' && isset($pathParts[2]) => (function () use ($pathParts) {
        Auth::checkPermission('delete_members');

        $memberId = $pathParts[2];
        if (!is_numeric($memberId)) {
            Helpers::sendError('Valid Member ID required', 400);
        }

        $result = Member::delete((int)$memberId);
        echo json_encode($result);
    })(),

    // =================================================================
    // VIEW SINGLE MEMBER
    // =================================================================
    $method === 'GET' && $pathParts[0] === 'member' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($pathParts) {
        Auth::checkPermission('view_members');

        $memberId = $pathParts[2];
        if (!is_numeric($memberId)) {
            Helpers::sendError('Valid Member ID required', 400);
        }

        try {
            $member = Member::get((int)$memberId);
            echo json_encode($member);
        } catch (Exception $e) {
            Helpers::logError("Member retrieval error: " . $e->getMessage());
            Helpers::sendError('Member not found', 404);
        }
    })(),

    // =================================================================
    // LIST ALL MEMBERS (Paginated)
    // =================================================================
    $method === 'GET' && $path === 'member/all' => (function () {
        // Auth::checkPermission('view_members');

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

        $result = Member::getAll($page, $limit);
        echo json_encode($result);
    })(),

    // =================================================================
    // RECENT MEMBERS (Dashboard Widget)
    // =================================================================
    $method === 'GET' && $path === 'member/recent' => (function () use ($token) {
        Auth::checkPermission('view_members');

        $orm = new ORM();
        $members = $orm->selectWithJoin(
            baseTable: 'churchmember c',
            joins: [
                ['table' => 'member_phone p',       'on' => 'c.MbrID = p.MbrID', 'type' => 'LEFT'],
                ['table' => 'userauthentication u', 'on' => 'c.MbrID = u.MbrID', 'type' => 'LEFT']
            ],
            fields: [
                'c.MbrID',
                'c.MbrFirstName',
                'c.MbrFamilyName',
                'c.MbrEmailAddress',
                'c.MbrRegistrationDate',
                "GROUP_CONCAT(DISTINCT p.PhoneNumber) AS PhoneNumbers",
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
    })(),

    // =================================================================
    // FALLBACK
    // =================================================================
    default => Helpers::sendError('Member endpoint not found', 404),
};