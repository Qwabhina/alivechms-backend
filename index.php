<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/core/ORM.php';
require_once __DIR__ . '/core/Auth.php';


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


header('Content-Type: application/json');

// $path = $_SERVER['REQUEST_URI'] ?? '';
$path = $_GET['path'] ?? '';
$pathParts = explode('/', trim($path, '/'));
$section = $pathParts[0] ?? '';

try {
    $routes = [
        'auth'         => 'AuthRoutes.php',
        'secure'       => 'AuthRoutes.php',
        'contribution' => 'ContributionRoutes.php',
        'search'       => 'SearchRoutes.php',
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
