<?php

/**
 * Dashboard API Routes – v1
 *
 * Single, powerful endpoint providing a comprehensive real-time overview
 * for church leadership:
 * - Membership statistics
 * - Financial summary
 * - Recent attendance trends
 * - Upcoming events
 * - Pending approvals
 * - Recent activity feed
 *
 * Fully branch-aware and permission-controlled.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Dashboard.php';

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

    // DASHBOARD OVERVIEW
    $method === 'GET' && $path === 'dashboard/overview' => (function () use ($token) {
        Auth::checkPermission('view_dashboard');

        try {
            $overview = Dashboard::getOverview();
            echo json_encode($overview);
        } catch (Exception $e) {
            Helpers::logError("Dashboard generation failed: " . $e->getMessage());
            Helpers::sendFeedback('Failed to generate dashboard', 500);
        }
    })(),

    // FALLBACK
    default => Helpers::sendFeedback('Dashboard endpoint not found', 404),
};