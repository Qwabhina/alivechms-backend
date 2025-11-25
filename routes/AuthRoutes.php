<?php

/**
 * Authentication API Routes – v1
 *
 * Handles login, token refresh, and logout.
 * Public endpoints — no token required.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Auth.php';

match (true) {

    // =================================================================
    // LOGIN
    // =================================================================
    $method === 'POST' && $path === 'auth/login' => (function () {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload) || empty($payload['userid']) || empty($payload['passkey'])) {
            Helpers::sendFeedback('Username and password required', 400);
        }
        try {
            $result = Auth::login($payload['userid'], $payload['passkey']);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback('Invalid credentials', 401);
        }
    })(),

    // =================================================================
    // REFRESH TOKEN
    // =================================================================
    $method === 'POST' && $path === 'auth/refresh' => (function () {
        $payload = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $payload['refresh_token'] ?? '';
        if ($refreshToken === '') {
            Helpers::sendFeedback('Refresh token required', 400);
        }
        try {
            $result = Auth::refreshAccessToken($refreshToken);
            echo json_encode($result);
        } catch (Exception $e) {
            Helpers::sendFeedback('Invalid or expired refresh token', 401);
        }
    })(),

    // =================================================================
    // LOGOUT
    // =================================================================
    $method === 'POST' && $path === 'auth/logout' => (function () {
        $payload = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $payload['refresh_token'] ?? '';
        if ($refreshToken === '') {
            Helpers::sendFeedback('Refresh token required', 400);
        }
        Auth::logout($refreshToken);
    })(),

    // =================================================================
    // FALLBACK
    // =================================================================
    default => Helpers::sendFeedback('Auth endpoint not found', 404),
};