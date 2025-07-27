<?php

/**
 * Auth API Routes
 * This file handles authentication-related API routes for the AliveChMS backend.
 * It provides endpoints for user login, token refresh, and logout.
 * Requires POST requests for login and refresh, and a valid Bearer token for logout.
 */


if ($_SERVER["REQUEST_METHOD"] !== 'POST') Helpers::sendError('Request Malformed', 405);

switch ($path) {
    case 'auth/login':
        $input = json_decode(file_get_contents("php://input"), true);
        $output = Auth::login($input['userid'], $input['passkey']);
        echo json_encode($output);
        break;

    case 'auth/refresh':
        $data = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) Helpers::sendError('Refresh token required', 401);

        try {
            $result = Auth::refreshAccessToken($refreshToken);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendError('Refresh token required', 401);
            Helpers::logError($e->getMessage());
        }
        break;

    case 'auth/logout':
        $token = Auth::getBearerToken();
        if (!$token || !($decoded = Auth::verify($token))) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        try {
            $orm = new ORM();
            $orm->update('refresh_tokens', ['revoked' => 1], ['user_id' => $decoded['user_id'], 'revoked' => 0]);
            echo json_encode(['message' => 'Logged out successfully']);
        } catch (Exception $e) {
            error_log('Auth: Logout failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to log out']);
        }
        break;

    default:
        Helpers::sendError('Endpoint not found', 404);
        break;
}