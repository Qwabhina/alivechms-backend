<?php
// === FILE: ContributionRoutes.php ===
switch ($path) {
    case 'contribution/average':
        $input = json_decode(file_get_contents("php://input"), true);
        $month = $input['month'] ?? date('Y-m');
        $contributions = (new ORM())->runQuery('SELECT * FROM contribution WHERE MONTH(date) = :month', ['month' => $month]);
        $average = array_sum(array_column($contributions, 'amount')) / count($contributions);
        echo json_encode(['average_contribution' => $average]);
        break;
}
