<?php

/**
 * Contribution API Routes – RESTful & Convention-Compliant
 *
 * Endpoints:
 * /contribution/create
 * /contribution/update/{id}
 * /contribution/delete/{id}
 * /contribution/restore/{id}
 * /contribution/view/{id}
 * /contribution/all
 * /contribution/total
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

require_once __DIR__ . '/../core/Contribution.php';

if (!$token || !Auth::verify($token)) {
    Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

$action     = $pathParts[1] ?? '';
$resourceId = $pathParts[2] ?? null;

switch ("$method $action") {

    case 'POST create':
        Auth::checkPermission($token, 'create_contribution');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            Helpers::sendFeedback('Invalid JSON payload', 400);
        }
        $result = Contribution::create($payload);
        echo json_encode($result);
        break;

    case 'PUT update':
        Auth::checkPermission($token, 'edit_contribution');
        if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Contribution ID is required in URL', 400);
        }
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = Contribution::update((int)$resourceId, $payload);
        echo json_encode($result);
        break;

    case 'DELETE delete':
        Auth::checkPermission($token, 'delete_contribution');
        if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Contribution ID is required in URL', 400);
        }
        $result = Contribution::delete((int)$resourceId);
        echo json_encode($result);
        break;

    case 'POST restore':
        Auth::checkPermission($token, 'delete_contribution');
        if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Contribution ID is required in URL', 400);
        }
        $result = Contribution::restore((int)$resourceId);
        echo json_encode($result);
        break;

    case 'GET view':
        Auth::checkPermission($token, 'view_contribution');
        if (!$resourceId || !is_numeric($resourceId)) {
            Helpers::sendFeedback('Contribution ID is required in URL', 400);
        }
        $contribution = Contribution::get((int)$resourceId);
        echo json_encode($contribution);
        break;

    case 'GET all':
        Auth::checkPermission($token, 'view_contribution');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = max(1, min(100, (int)($_GET['limit'] ?? 10)));
        $filters = [];
        foreach (['contribution_type_id', 'member_id', 'fiscal_year_id', 'start_date', 'end_date'] as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $filters[$key] = $_GET[$key];
            }
        }
        $result = Contribution::getAll($page, $limit, $filters);
        echo json_encode($result);
        break;

    case 'GET total':
        Auth::checkPermission($token, 'view_contribution');
        $filters = [];
        foreach (['contribution_type_id', 'member_id', 'fiscal_year_id', 'start_date', 'end_date'] as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $filters[$key] = $_GET[$key];
            }
        }
        $result = Contribution::getTotal($filters);
        echo json_encode($result);
        break;

    default:
        Helpers::sendFeedback('Contribution endpoint not found', 404);
}