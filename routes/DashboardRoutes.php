<?php

switch ($path) {
    case 'dashboard/overview':
        $input = json_decode(file_get_contents("php://input"), true);
        $user = Auth::verify(Auth::getBearerToken());
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            break;
        }

        $items = (new ORM())->runQuery("SELECT * FROM `items` WHERE `user_id` = :user_id", ['user_id' => $user->id]);
        echo json_encode(['items' => $items]);
        break;

    case 'dashboard/stats':
        $input = json_decode(file_get_contents("php://input"), true);
        $user = Auth::verify(Auth::getBearerToken());
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            break;
        }

        $stats = (new ORM())->runQuery("SELECT COUNT(*) as total_items FROM `items` WHERE `user_id` = :user_id", ['user_id' => $user->id]);
        echo json_encode(['stats' => $stats]);
        break;

    case 'dashboard/notifications':
        $input = json_decode(file_get_contents("php://input"), true);
        $user = Auth::verify(Auth::getBearerToken());
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            break;
        }

        $notifications = (new ORM())->runQuery("SELECT * FROM `notifications` WHERE `user_id` = :user_id", ['user_id' => $user->id]);
        echo json_encode(['notifications' => $notifications]);
        break;
}
