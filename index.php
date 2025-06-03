<?php

/**
 * AliveChMS Backend API
 * This file serves as the entry point for the API, handling routing and initialization.
 * It sets up the environment, loads necessary libraries, and routes requests to the appropriate handlers.
 * @package AliveChMS
 * @version 1.0.0
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set("Africa/Accra");

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/core/ORM.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Helpers.php';

header('Content-Type: application/json');
Helpers::addCorsHeaders();

$path = $_GET['path'] ?? '';
$pathParts = explode('/', trim($path, '/'));
$section = $pathParts[0] ?? '';

try {
    $routes = [
        'auth' => 'AuthRoutes.php',
        'secure' => 'AuthRoutes.php',
        'contribution' => 'ContributionRoutes.php',
        'search' => 'SearchRoutes.php',
        'member' => 'MemberRoutes.php',
        'family' => 'FamilyRoutes.php',
        'dashboard' => 'DashboardRoutes.php',
        'expense' => 'ExpenseRoutes.php',
        'event' => 'EventRoutes.php',
        'expensecategory' => 'ExpenseCategoryRoutes.php',
        'budget' => 'BudgetRoutes.php',
        'finance' => 'FinanceRoutes.php'
    ];

    if (!array_key_exists($section, $routes)) {
        Helpers::sendError('Endpoint not found', 404);
    }

    require_once __DIR__ . '/routes/' . $routes[$section];
} catch (Exception $e) {
    Helpers::sendError($e->getMessage(), 400);
}
?>