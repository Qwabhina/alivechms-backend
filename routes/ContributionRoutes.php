<?php

/**
 * Contribution API Routes – v1
 *
 * Full financial contribution management:
 * - Create new contribution
 * - Update existing contribution
 * - Soft-delete & restore
 * - View single contribution
 * - Paginated listing with powerful filtering
 * - Totals reporting
 *
 * All operations strictly permission-controlled.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Contribution.php';

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

    // CREATE CONTRIBUTION
    $method === 'POST' && $path === 'contribution/create' => (function () use ($token) {
        Auth::checkPermission($token, 'create_contribution');

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
        }

        $result = Contribution::create($payload);
        echo json_encode($result);
    })(),

    // UPDATE CONTRIBUTION
    $method === 'PUT' && $pathParts[0] === 'contribution' && ($pathParts[1] ?? '') === 'update' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
        Auth::checkPermission($token, 'edit_contribution');

        $contributionId = $pathParts[2];
        if (!is_numeric($contributionId)) {
            Helpers::sendFeedback('Valid Contribution ID required', 400);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
        }

        $result = Contribution::update((int)$contributionId, $payload);
        echo json_encode($result);
    })(),

    // SOFT DELETE CONTRIBUTION
    $method === 'DELETE' && $pathParts[0] === 'contribution' && ($pathParts[1] ?? '') === 'delete' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
        Auth::checkPermission($token, 'delete_contribution');

        $contributionId = $pathParts[2];
        if (!is_numeric($contributionId)) {
            Helpers::sendFeedback('Valid Contribution ID required', 400);
        }

        $result = Contribution::delete((int)$contributionId);
        echo json_encode($result);
    })(),

    // RESTORE SOFT-DELETED CONTRIBUTION
    $method === 'POST' && $pathParts[0] === 'contribution' && ($pathParts[1] ?? '') === 'restore' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
        Auth::checkPermission($token, 'delete_contribution');

        $contributionId = $pathParts[2];
        if (!is_numeric($contributionId)) {
            Helpers::sendFeedback('Valid Contribution ID required', 400);
        }

        $result = Contribution::restore((int)$contributionId);
        echo json_encode($result);
    })(),

    // VIEW SINGLE CONTRIBUTION
    $method === 'GET' && $pathParts[0] === 'contribution' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
        Auth::checkPermission($token, 'view_contribution');

        $contributionId = $pathParts[2];
        if (!is_numeric($contributionId)) {
            Helpers::sendFeedback('Valid Contribution ID required', 400);
        }

        $result = Contribution::get((int)$contributionId);
        echo json_encode($result);
    })(),

    // LIST ALL CONTRIBUTIONS (Paginated + Filtered)
    $method === 'GET' && $path === 'contribution/all' => (function () use ($token) {
        Auth::checkPermission($token, 'view_contribution');

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

        $filters = [];
        foreach (
            [
                'contribution_type_id',
                'member_id',
                'fiscal_year_id',
                'start_date',
                'end_date'
            ] as $key
        ) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $filters[$key] = $_GET[$key];
            }
        }

        $result = Contribution::getAll($page, $limit, $filters);
        echo json_encode($result);
    })(),

    // TOTAL CONTRIBUTIONS (Reporting)
    $method === 'GET' && $path === 'contribution/total' => (function () use ($token) {
        Auth::checkPermission($token, 'view_contribution');

        $filters = [];
        foreach (
            [
                'contribution_type_id',
                'member_id',
                'fiscal_year_id',
                'start_date',
                'end_date'
            ] as $key
        ) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $filters[$key] = $_GET[$key];
            }
        }

        $result = Contribution::getTotal($filters);
        echo json_encode($result);
    })(),

    // FALLBACK
    default => Helpers::sendFeedback('Contribution endpoint not found', 404),
};