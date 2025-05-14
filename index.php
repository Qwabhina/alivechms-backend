<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/core/ORM.php';
require_once __DIR__ . '/core/Auth.php';

header('Access-Control-Allow-Origin: *'); // Adjust to specific origin in production
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

header('Content-Type: application/json');


$path = $_GET['path'] ?? '';
$pathParts = explode('/', trim($path, '/'));
$section = $pathParts[0] ?? '';

try {
    $routes = [
        'auth'         => 'AuthRoutes.php',
        'secure'       => 'AuthRoutes.php',
        'contribution' => 'ContributionRoutes.php',
        'search'       => 'SearchRoutes.php',
        'member'       => 'MemberRoutes.php',
        'dashboard'    => 'DashboardRoutes.php',
        'paginate'     => 'PaginationRoutes.php',
        'soft-delete'  => 'PaginationRoutes.php',
        'upload'       => 'UploadRoutes.php',
        'download'     => 'FileRoutes.php',
    ];

    if (!array_key_exists($section, $routes)) {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found. Please check the URL.']);
        exit;
    }

    require_once __DIR__ . '/routes/' . $routes[$section];
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
