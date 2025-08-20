<?php

/**
 * Dashboard API Routes
 * This file handles the dashboard-related API routes for the AliveChMS backend.
 * It provides endpoints for fetching dashboard highlights and statistics.
 * Requires authentication via a Bearer token and only allows GET requests.
 */
require_once __DIR__ . '/../core/Dashboard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Helpers::sendFeedback('Method not allowed', 405);

if (!$token || !Auth::verify($token)) Helpers::sendFeedback('Unauthorized', 401);

switch ($path) {
    case 'dashboard/highlights':
        $highlights = Dashboard::getHighlights();
        echo json_encode($highlights);
        // Uncomment the line below if you want to send feedback in a different format
        // Helpers::sendFeedback($highlights, 200);
        break;

    default:
        Helpers::sendFeedback('Endpoint not found', 404);
        break;
}
?>