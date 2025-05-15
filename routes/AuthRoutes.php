<?php
// === FILE: AuthRoutes.php ===

if ($_SERVER["REQUEST_METHOD"] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

switch ($path) {
    case 'auth/login':
        $input = json_decode(file_get_contents("php://input"), true);
        $output = Auth::login($input['userid'], $input['passkey']);
        echo json_encode($output);
        break;

    case 'auth/refresh':
        $data = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            http_response_code(400);
            echo json_encode(['error' => 'Refresh token required']);
            exit;
        }

        try {
            $result = Auth::refreshAccessToken($refreshToken);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => $e->getMessage()]);
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
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found. Please check the URL.']);
        break;
}