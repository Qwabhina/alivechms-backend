<?php

/**
 * Authentication API Routes
 *
 * Handles login, token refresh, and logout.
 * All responses are standardized JSON.
 *
 * @package AliveChMS\Routes
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-20
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::sendFeedback('Method not allowed', 405);
}

switch ($path) {

    case 'auth/login':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['userid']) || empty($input['passkey'])) {
            Helpers::sendFeedback('Username and password required', 400);
        }
        try {
            $result = Auth::login($input['userid'], $input['passkey']);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback('Login failed', 401);
        }
        break;

    case 'auth/refresh':
        $input = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $input['refresh_token'] ?? '';
        if (empty($refreshToken)) {
            Helpers::sendFeedback('Refresh token required', 400);
        }
        try {
            $result = Auth::refreshAccessToken($refreshToken);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback('Invalid or expired refresh token', 401);
        }
        break;

    case 'auth/logout':
        $input = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $input['refresh_token'] ?? '';
        if (empty($refreshToken)) {
            Helpers::sendFeedback('Refresh token required', 400);
        }
        try {
            Auth::logout($refreshToken);
        } catch (Exception $e) {
            Helpers::sendFeedback('Logout failed', 500);
        }
        break;

    default:
        Helpers::sendFeedback('Endpoint not found', 404);
}