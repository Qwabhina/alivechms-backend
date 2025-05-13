<?php
// === FILE: AuthRoutes.php ===
switch ($path) {
    case 'auth/login':
        $input = json_decode(file_get_contents("php://input"), true);
        $token = Auth::login($input['username'], $input['password'], 'userauthentication');
        echo json_encode(['token' => $token]);
        break;

    case 'secure/user-data':
        $user = Auth::verify(Auth::getBearerToken());
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            break;
        }
        echo json_encode(['message' => 'Secure content', 'user' => $user]);
        break;

    case 'secure/role-check':
        $user = Auth::verify(Auth::getBearerToken());
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            break;
        }

        $input = json_decode(file_get_contents("php://input"), true);
        if (!in_array($user->role, $input['roles'] ?? [])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            break;
        }

        echo json_encode(['message' => 'Access granted', 'role' => $user->role]);
        break;
}
