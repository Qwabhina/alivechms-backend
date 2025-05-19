<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Member.php';
require_once __DIR__ . '/../core/Helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$token = Auth::getBearerToken();
$pathParts = explode('/', trim($path, '/'));

if (!$token || !Auth::verify($token)) {
    Helpers::sendError('Unauthorized', 401);
}

switch ($method . ' ' . ($pathParts[0] ?? '') . '/' . ($pathParts[1] ?? '')) {
    case 'GET member/recent':
        Auth::checkPermission($token, 'view_members');

        $orm = new ORM();
        $members = $orm->selectWithJoin(
            baseTable: 'churchmember c',
            joins: [
                ['table' => 'member_phone p', 'on' => 'c.MbrID = p.MbrID', 'type' => 'LEFT OUTER'],
                ['table' => 'userauthentication u', 'on' => 'c.MbrID = u.MbrID', 'type' => 'LEFT OUTER']
            ],
            fields: ['c.*', 'GROUP_CONCAT(p.PhoneNumber) as PhoneNumbers', 'u.Username', 'u.LastLoginAt'],
            conditions: ['c.MbrMembershipStatus' => ':status'],
            params: [':status' => 'Active'],
            orderBy: ['c.MbrRegistrationDate' => 'DESC'],
            groupBy: ['c.MbrID'],
            limit: 10
        );

        echo json_encode($members);

        break;

    case 'GET member/all':
        Auth::checkPermission($token, 'view_members');
        $orm = new ORM();
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
        $offset = ($page - 1) * $limit;

        $members = $orm->selectWithJoin(
            baseTable: 'churchmember c',
            joins: [['table' => 'member_phone p', 'on' => 'c.MbrID = p.MbrID', 'type' => 'LEFT']],
            fields: ['c.*', 'GROUP_CONCAT(p.PhoneNumber) as PhoneNumbers'],
            conditions: ['c.MbrMembershipStatus' => ':status'],
            params: [':status' => 'Active'],
            groupBy: ['c.MbrID'],
            limit: $limit,
            offset: $offset
        );

        $total = $orm->runQuery("SELECT COUNT(*) as total FROM churchmember WHERE MbrMembershipStatus = 'Active'")[0]['total'];

        echo json_encode([
            'data' => $members,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        break;

    case 'POST member/create':
        Auth::checkPermission($token, 'edit_members');

        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $result = Member::register($input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    case 'PUT member/update':
        Auth::checkPermission($token, 'edit_members');

        $input = json_decode(file_get_contents('php://input'), true);
        $mbrId = $pathParts[2] ?? null;
        if (!$mbrId) {
            Helpers::sendError('Member ID required', 400);
        }

        try {
            $result = Member::update($mbrId, $input);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }

        break;

    case "DELETE member/delete":
        Auth::checkPermission($token, 'edit_members');

        $mbrId = $pathParts[2] ?? null;
        if (!$mbrId) {
            Helpers::sendError('Member ID required', 400);
        }
        try {
            $result = Member::delete($mbrId);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 400);
        }
        break;

    case 'GET member/view':
        Auth::checkPermission($token, 'view_members');
        $mbrId = $pathParts[2] ?? null;
        if (!$mbrId) {
            Helpers::sendError('Member ID required', 400);
        }
        try {
            $member = Member::get($mbrId);
            echo json_encode($member);
        } catch (Exception $e) {
            Helpers::sendError($e->getMessage(), 404);
        }
        break;

    default:
        Helpers::sendError('Endpoint not found', 404);
}
?>