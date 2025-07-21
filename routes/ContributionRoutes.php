<?php

/**
 * Contribution API Routes
 * This file handles all routes related to contributions, including creation, updating, deletion, and retrieval.
 * It checks for authentication and permissions before processing requests.
 * It uses the Contribution model for database interactions and returns JSON responses.
 * Requires authentication via a Bearer token and appropriate permissions.
 */

require_once __DIR__ . '/../core/Contribution.php';

if (!$token || !Auth::verify($token)) Helpers::sendError('Unauthorized', 401);

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
    case 'POST contribution/create':
        // Auth::checkPermission($token, 'create_contribution');
        $input = json_decode(file_get_contents('php://input'), true);

        try {
            $result = Contribution::create($input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    case 'POST contribution/update':
        // Auth::checkPermission($token, 'update_contribution');
        $contributionId = $pathParts[2] ?? null;
        if (!$contributionId) Helpers::sendError('Contribution ID required', 400);

        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $result = Contribution::update($contributionId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    case 'DELETE contribution/delete':
        // Auth::checkPermission($token, 'delete_contribution');
        $contributionId = $pathParts[2] ?? null;

        if (!$contributionId) Helpers::sendError('Contribution ID required', 400);

        try {
            $result = Contribution::delete($contributionId);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    case 'POST contribution/restore':
        // Auth::checkPermission($token, 'restore_contribution');
        $contributionId = $pathParts[2] ?? null;

        if (!$contributionId) Helpers::sendError('Contribution ID required', 400);

        try {
            $result = Contribution::restore($contributionId);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    case 'GET contribution/view':
        // Auth::checkPermission($token, 'view_contribution');
        $contributionId = $pathParts[2] ?? null;

        if (!$contributionId) Helpers::sendError('Contribution ID required', 400);

        try {
            $contribution = Contribution::get($contributionId);
            echo json_encode($contribution);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 404);
        }
        break;

    case 'GET contribution/all':
        // Auth::checkPermission($token, 'view_contribution');
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
        $filters = [];

        if (isset($_GET['contribution_type']) && is_numeric($_GET['contribution_type'])) $filters['contribution_type'] = intval($_GET['contribution_type']);
        if (isset($_GET['payment_option']) && is_numeric($_GET['payment_option'])) $filters['payment_option'] = intval($_GET['payment_option']);
        if (isset($_GET['fiscal_year']) && !empty($_GET['fiscal_year'])) $filters['fiscal_year'] = intval($_GET['fiscal_year']);
        if (isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
        if (isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];
        if (!empty($filters['start_date']) && !empty($filters['end_date']) && $filters['start_date'] > $filters['end_date']) Helpers::sendError('start_date must be before end_date', 400);

        try {
            $result = Contribution::getAll($page, $limit, $filters);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::logError("Contribution retrieval error" . $e->getMessage(), 400);
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    case 'GET contribution/average':
        // Auth::checkPermission($token, 'view_contribution');

        $filters = [];

        if (isset($_GET['contribution_type']) && is_numeric($_GET['contribution_type'])) $filters['contribution_type'] = intval($_GET['contribution_type']);

        if (isset($_GET['contributor_id']) && is_numeric($_GET['contributor_id'])) $filters['contributor_id'] = intval($_GET['contributor_id']);
        if (isset($_GET['payment_option']) && is_numeric($_GET['payment_option'])) $filters['payment_option'] = intval($_GET['payment_option']);
        if (isset($_GET['fiscal_year']) && !empty($_GET['fiscal_year'])) $filters['fiscal_year'] = intval($_GET['fiscal_year']);
        if (isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
        if (isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];
        if (!empty($filters['start_date']) && !empty($filters['end_date']) && $filters['start_date'] > $filters['end_date']) Helpers::sendError('start_date must be before end_date', 400);

        if (empty($filters['start_date']) && empty($filters['end_date']) && empty($filters['fiscal_year'])) Helpers::sendError('At least one of start_date, end_date, or fiscal_year is required.', 400);

        try {
            $result = Contribution::getAverage($filters);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    case 'GET contribution/total':
        // Auth::checkPermission($token, 'view_contribution');
        $filters = [];

        if (isset($_GET['contribution_type']) && is_numeric($_GET['contribution_type'])) $filters['contribution_type'] = intval($_GET['contribution_type']);
        if (isset($_GET['contributor_id']) && is_numeric($_GET['contributor_id'])) $filters['contributor_id'] = intval($_GET['contributor_id']);
        if (isset($_GET['payment_option']) && is_numeric($_GET['payment_option'])) $filters['payment_option'] = intval($_GET['payment_option']);
        if (isset($_GET['fiscal_year']) && !empty($_GET['fiscal_year'])) $filters['fiscal_year'] = intval($_GET['fiscal_year']);
        if (isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
        if (isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];
        if (!empty($filters['start_date']) && !empty($filters['end_date']) && $filters['start_date'] > $filters['end_date']) Helpers::sendError('start_date must be before end_date', 400);

        if (empty($filters['start_date']) && empty($filters['end_date']) && empty($filters['fiscal_year'])) Helpers::sendError('At least one of start_date, end_date, or fiscal_year is required.', 400);

        try {
            $result = Contribution::getTotal($filters);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    default:
        Helpers::sendError('Endpoint not found', 404);
}
?>