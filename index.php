<?php

/**
 * AliveChMS Backend API - Entry Point
 *
 * This file serves as the single entry point for the REST API.
 * It initializes the environment, loads dependencies, sets security headers,
 * and routes requests to appropriate handlers.
 *
 * @package AliveChMS
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-19
 */

declare(strict_types=1);

// Prevent direct access if not via web server
if (php_sapi_name() === 'cli') {
    die('This is a web-only API entry point.');
}

// Disable error display in production (errors are logged instead)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

date_default_timezone_set('Africa/Accra');

// Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Core dependencies
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/ORM.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Helpers.php';

// Security headers
header('Content-Type: application/json; charset=utf-8');
Helpers::addCorsHeaders();

// Prevent path traversal attacks
$rawPath = $_GET['path'] ?? '';

// Prevent directory traversal and normalize
$path = trim($rawPath, '/');
$path = preg_replace('#/{2,}#', '/', $path); // Remove double slashes
$path = htmlspecialchars($path, ENT_QUOTES, 'UTF-8'); // Final safety

if (str_contains($path, '..') || str_contains($path, '\0')) {
    Helpers::logError("Path traversal attempt: $path");
    Helpers::sendFeedback('Invalid request path', 400);
}

if ($path === '') Helpers::sendFeedback('Welcome to AliveChMS API', 200, 'success');

$pathParts = $path !== '' ? explode('/', $path) : [];
$section = $pathParts[0] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$token = Auth::getBearerToken();

try {
    $routes = [
        'auth'             => 'AuthRoutes.php',
        'budget'           => 'BudgetRoutes.php',
        'contribution'     => 'ContributionRoutes.php',
        'dashboard'        => 'DashboardRoutes.php',
        'event'            => 'EventRoutes.php',
        'expensecategory'  => 'ExpenseCategoryRoutes.php',
        'expense'          => 'ExpenseRoutes.php',
        'family'           => 'FamilyRoutes.php',
        'finance'          => 'FinanceRoutes.php',
        'fiscalyear'       => 'FiscalYearRoutes.php',
        'group'            => 'GroupRoutes.php',
        'member'           => 'MemberRoutes.php',
        'membershiptype'   => 'MembershipTypeRoutes.php',
        'permission'       => 'PermissionRoutes.php',
        'role'             => 'RoleRoutes.php',
    ];

    if (!array_key_exists($section, $routes)) {
        Helpers::sendFeedback('Endpoint not found', 404);
    }

    $routeFile = __DIR__ . '/routes/' . $routes[$section];

    if (!file_exists($routeFile)) {
        Helpers::logError("Route file missing: $routeFile");
        Helpers::sendFeedback('Internal server error', 500);
    }

    require_once $routeFile;
} catch (Throwable $e) {
    Helpers::logError('Uncaught exception in index.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Helpers::sendFeedback('Internal server error', 500);
}