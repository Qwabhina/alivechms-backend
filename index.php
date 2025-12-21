<?php

/**
 * AliveChMS Backend API – Main Entry Point
 *
 * Single entry point for the entire REST API.
 * Initialises environment, security headers, loads dependencies,
 * and dispatches requests via clean URL routing.
 *
 * @package  AliveChMS
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

// Block CLI access
if (php_sapi_name() === 'cli') {
    die('Web-only access permitted.');
}

// Production error handling
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

date_default_timezone_set('Africa/Accra');

// Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment
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

// Extract clean path
$rawPath = $_GET['path'] ?? '';
$path    = trim($rawPath, '/');
$path    = preg_replace('#/{2,}#', '/', $path); // Remove double slashes

// Block path traversal
if (str_contains($path, '..') || str_contains($path, "\0")) {
    Helpers::logError("Path traversal blocked: $rawPath");
    Helpers::sendFeedback('Invalid request path', 400);
}

if ($path === '') {
    header('Location: /public');
    exit;
}

$pathParts = $path !== '' ? explode('/', $path) : [];
$section   = $pathParts[0] ?? '';
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$token     = Auth::getBearerToken();

// Master route map
$routes = [
    'auth'            => 'AuthRoutes.php',
    'member'          => 'MemberRoutes.php',
    'family'          => 'FamilyRoutes.php',
    'contribution'    => 'ContributionRoutes.php',
    'pledge'          => 'PledgeRoutes.php',
    'expense'         => 'ExpenseRoutes.php',
    'expensecategory' => 'ExpenseCategoryRoutes.php',
    'budget'          => 'BudgetRoutes.php',
    'event'           => 'EventRoutes.php',
    'dashboard'       => 'DashboardRoutes.php',
    'finance'         => 'FinanceRoutes.php',
    'fiscalyear'      => 'FiscalYearRoutes.php',
    'group'           => 'GroupRoutes.php',
    'grouptype'       => 'GroupRoutes.php',
    'membershiptype'  => 'MembershipTypeRoutes.php',
    'role'            => 'RoleRoutes.php',
    'permission'      => 'PermissionRoutes.php',
    'volunteer'       => 'VolunteerRoutes.php',
];

if (!isset($routes[$section])) {
    Helpers::sendFeedback('Endpoint not found', 404);
}

$routeFile = __DIR__ . '/routes/' . $routes[$section];

if (!file_exists($routeFile)) {
    Helpers::logError("Missing route file: $routeFile");
    Helpers::sendFeedback('Internal server error', 500);
}

require_once $routeFile;