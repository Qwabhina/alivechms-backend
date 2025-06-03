<?php

/**
 * Contribution API Routes
 * This file handles contribution-related API routes for the AliveChMS backend.
 * It provides endpoints for fetching average contributions and listing all contributions.
 * Requires authentication via a Bearer token and appropriate permissions.
 */

require_once __DIR__ . '/../core/Contribution.php';

if (!$token || !Auth::verify($token)) Helpers::sendError('Unauthorized', 401);

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
    case 'GET contribution/average':
        Auth::checkPermission($token, 'view_contributions');

        $month = $pathParts[3] ?? null;

        try {
            Helpers::validateInput($pathParts, [2 => 'required']);

            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                throw new Exception('Invalid month format (YYYY-MM) ' . $month);
            }

            $orm = new ORM();
            $contributions = $orm->runQuery(
                'SELECT ContributionAmount FROM contribution WHERE DATE_FORMAT(ContributionDate, "%Y-%m") = :month',
                ['month' => $month]
            );

            $total = array_sum(array_column($contributions, 'ContributionAmount'));
            $count = count($contributions);
            $average = $count ? $total / $count : 0;

            echo json_encode(['average_contribution' => number_format($average, 2)]);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }

        break;

    case 'GET contribution/all':
        Auth::checkPermission($token, 'view_contributions');

        $orm = new ORM();
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
        $offset = ($page - 1) * $limit;

        $contributions = $orm->selectWithJoin(
            baseTable: 'contribution c',
            joins: [
                ['table' => 'churchmember m', 'on' => 'c.MbrID = m.MbrID', 'type' => 'LEFT'],
                ['table' => 'contributiontype ct', 'on' => 'c.ContributionTypeID = ct.ContributionTypeID', 'type' => 'LEFT']
            ],
            fields: ['c.*', 'm.MbrFirstName', 'm.MbrFamilyName', 'ct.ContributionTypeName'],
            limit: $limit,
            offset: $offset
        );

        $total = $orm->runQuery("SELECT COUNT(*) as total FROM contribution")[0]['total'];
        echo json_encode([
            'data' => $contributions,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);

        break;

    default:
        Helpers::sendError('Endpoint not found', 404);
}
?>