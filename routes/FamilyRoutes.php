<?php

/**
 * Family API Routes – v1
 *
 * Complete family unit management with member lifecycle:
 * - Create family with head of household
 * - Update family details
 * - Soft-delete family
 * - View single family with all members
 * - Paginated listing with filtering
 * - Add/remove/update member roles
 *
 * All operations fully permission-controlled and auditable.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Family.php';

// ---------------------------------------------------------------------
// AUTHENTICATION & AUTHORIZATION
// ---------------------------------------------------------------------
$token = Auth::getBearerToken();
if (!$token || Auth::verify($token) === false) {
    Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

// ---------------------------------------------------------------------
// ROUTE DISPATCHER
// ---------------------------------------------------------------------
match (true) {

    // =================================================================
    // CREATE FAMILY
    // =================================================================
    $method === 'POST' && $path === 'family/create' => (function () use ($token) {
        Auth::checkPermission('manage_families');

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
        }

        $result = Family::create($payload);
        echo json_encode($result);
    })(),

    // =================================================================
    // UPDATE FAMILY
    // =================================================================
    $method === 'PUT' && $pathParts[0] === 'family' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($pathParts) {
        Auth::checkPermission('manage_families');

        $familyId = $pathParts[2];
        if (!is_numeric($familyId)) {
            Helpers::sendFeedback('Valid Family ID required', 400);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
        }

        $result = Family::update((int)$familyId, $payload);
        echo json_encode($result);
    })(),

    // =================================================================
    // DELETE FAMILY
    // =================================================================
    $method === 'DELETE' && $pathParts[0] === 'family' && ($pathParts[1] ?? '') === 'delete' && isset($pathParts[2]) => (function () use ($pathParts) {
        Auth::checkPermission('manage_families');

        $familyId = $pathParts[2];
        if (!is_numeric($familyId)) {
            Helpers::sendFeedback('Valid Family ID required', 400);
        }

        $result = Family::delete((int)$familyId);
        echo json_encode($result);
    })(),

    // =================================================================
    // VIEW SINGLE FAMILY
    // =================================================================
    $method === 'GET' && $pathParts[0] === 'family' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($pathParts) {
        Auth::checkPermission('view_families');

        $familyId = $pathParts[2];
        if (!is_numeric($familyId)) {
            Helpers::sendFeedback('Valid Family ID required', 400);
        }

        $family = Family::get((int)$familyId);
        echo json_encode($family);
    })(),

    // =================================================================
    // LIST ALL FAMILIES (Paginated + Filtered)
    // =================================================================
    $method === 'GET' && $path === 'family/all' => (function () {
        Auth::checkPermission('view_families');

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = max(1, min(100, (int)($_GET['limit'] ?? 10)));
        $filters = [];

        if (!empty($_GET['branch_id']) && is_numeric($_GET['branch_id'])) {
            $filters['branch_id'] = (int)$_GET['branch_id'];
        }
        if (!empty($_GET['name'])) {
            $filters['name'] = trim($_GET['name']);
        }

        $result = Family::getAll($page, $limit, $filters);
        echo json_encode($result);
    })(),

    // =================================================================
    // ADD MEMBER TO FAMILY
    // =================================================================
    $method === 'POST' && $pathParts[0] === 'family' && ($pathParts[1] ?? '') === 'addMember' && isset($pathParts[2]) => (function () use ($pathParts) {
        Auth::checkPermission('manage_families');

        $familyId = $pathParts[2];
        if (!is_numeric($familyId)) {
            Helpers::sendFeedback('Valid Family ID required', 400);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload) || empty($payload['member_id']) || empty($payload['role'])) {
            Helpers::sendFeedback('member_id and role are required', 400);
        }

        $result = Family::addMember((int)$familyId, $payload);
        echo json_encode($result);
    })(),

    // =================================================================
    // REMOVE MEMBER FROM FAMILY
    // =================================================================
    $method === 'DELETE' && $pathParts[0] === 'family' && ($pathParts[1] ?? '') === 'removeMember' && isset($pathParts[2], $pathParts[3]) => (function () use ($pathParts) {
        Auth::checkPermission('manage_families');

        $familyId = $pathParts[2];
        $memberId = $pathParts[3];
        if (!is_numeric($familyId) || !is_numeric($memberId)) {
            Helpers::sendFeedback('Valid Family ID and Member ID required', 400);
        }

        $result = Family::removeMember((int)$familyId, (int)$memberId);
        echo json_encode($result);
    })(),

    // =================================================================
    // UPDATE MEMBER ROLE IN FAMILY
    // =================================================================
    $method === 'PUT' && $pathParts[0] === 'family' && ($pathParts[1] ?? '') === 'updateMemberRole' && isset($pathParts[2], $pathParts[3]) => (function () use ($pathParts) {
        Auth::checkPermission('manage_families');

        $familyId = $pathParts[2];
        $memberId = $pathParts[3];
        if (!is_numeric($familyId) || !is_numeric($memberId)) {
            Helpers::sendFeedback('Valid Family ID and Member ID required', 400);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload) || empty($payload['role'])) {
            Helpers::sendFeedback('Role is required', 400);
        }

        $result = Family::updateMemberRole((int)$familyId, (int)$memberId, $payload);
        echo json_encode($result);
    })(),

    // =================================================================
    // FALLBACK
    // =================================================================
    default => Helpers::sendFeedback('Family endpoint not found', 404),
};