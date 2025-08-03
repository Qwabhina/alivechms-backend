<?php

/**
 * Dashboard API Routes
 * This file handles the dashboard-related API routes for the AliveChMS backend.
 * It provides endpoints for fetching dashboard highlights and statistics.
 * Requires authentication via a Bearer token and only allows GET requests.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Helpers::sendFeedback('Method not allowed', 405);

if (!$token || !Auth::verify($token)) Helpers::sendFeedback('Unauthorized', 401);

switch ($path) {
    case 'dashboard/highlights':
        $orm = new ORM();

        // Total registered members
        $totalMembers = $orm->runQuery(
            "SELECT COUNT(*) as total FROM churchmember WHERE MbrMembershipStatus = :status",
            ['status' => 'Active']
        )[0]['total'] ?? 0;

        // Monthly revenue
        $monthlyRevenue = $orm->runQuery(
            "SELECT SUM(ContributionAmount) as total FROM contribution WHERE MONTH(ContributionDate) = MONTH(CURRENT_DATE()) AND YEAR(ContributionDate) = YEAR(CURRENT_DATE())"
        )[0]['total'] ?? 0;

        // Average midweek attendance (assuming churchevent has 'Midweek' events)
        $avgMidweek = $orm->runQuery(
            "SELECT AVG(cnt) as avg FROM (SELECT COUNT(*) as cnt FROM eventattendance ea JOIN churchevent e ON ea.EventID = e.EventID WHERE e.EventName LIKE '%Midweek%' AND MONTH(ea.AttendanceDate) = MONTH(CURRENT_DATE()) GROUP BY ea.EventID, ea.AttendanceDate) as sub"
        )[0]['avg'] ?? 0;

        // Average Sunday attendance
        $avgSunday = $orm->runQuery(
            "SELECT AVG(cnt) as avg FROM (SELECT COUNT(*) as cnt FROM eventattendance ea JOIN churchevent e ON ea.EventID = e.EventID WHERE e.EventName LIKE '%Sunday%' AND MONTH(ea.AttendanceDate) = MONTH(CURRENT_DATE()) GROUP BY ea.EventID, ea.AttendanceDate) as sub"
        )[0]['avg'] ?? 0;

        echo json_encode([
            'total_members' => $totalMembers,
            'monthly_revenue' => number_format($monthlyRevenue, 2),
            'avg_midweek_attendance' => round($avgMidweek),
            'avg_sunday_attendance' => round($avgSunday)
        ]);
        break;

    default:
        Helpers::sendFeedback('Endpoint not found', 404);
        break;
}
?>