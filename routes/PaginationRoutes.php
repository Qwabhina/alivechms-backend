<?php
// === FILE: PaginationRoutes.php ===
switch ($path) {
    case 'paginate/items':
        $input = json_decode(file_get_contents("php://input"), true);
        $page = $input['page'] ?? 1;
        $limit = $input['limit'] ?? 10;
        $table = $input['table'] ?? 'items';
        $offset = ($page - 1) * $limit;
        $items = (new ORM())->runQuery("SELECT * FROM `$table` LIMIT :limit OFFSET :offset", ['limit' => $limit, 'offset' => $offset]);
        echo json_encode(['items' => $items]);
        break;
}
