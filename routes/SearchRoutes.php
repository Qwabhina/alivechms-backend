<?php
// === FILE: SearchRoutes.php ===
switch ($path) {
    case 'search/items':
        $input = json_decode(file_get_contents("php://input"), true);
        $searchQuery = $input['query'];
        $table = $input['table'] ?? 'items';
        $results = (new ORM())->runQuery("SELECT * FROM `$table` WHERE CONCAT(name, description) LIKE :query", ['query' => "%$searchQuery%"]);
        echo json_encode(['results' => $results]);
        break;
}
