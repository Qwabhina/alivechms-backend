<?php

/**
 * Dashboard API Routes
 *
 * Single endpoint: /dashboard/overview
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

require_once __DIR__ . '/../core/Dashboard.php';

if (!$token || !Auth::verify($token)) {
    Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

if ($path !== 'dashboard/overview' || $method !== 'GET') {
    Helpers::sendFeedback('Endpoint not found', 404);
}

Auth::checkPermission($token, 'view_dashboard');

try {
    $overview = Dashboard::getOverview();
    echo json_encode($overview);
} catch (Exception $e) {
    Helpers::logError("Dashboard error: " . $e->getMessage());
    Helpers::sendFeedback('Failed to load dashboard', 500);
}