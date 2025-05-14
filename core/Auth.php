<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    private static $accessTokenTTL = 1800;       // 30 minutes
    private static $refreshTokenTTL = 86400;   // 1 day
    private static $secretKey;
    private static $refreshSecretKey;

    private static function initKeys()
    {
        if (!self::$secretKey) {
            self::$secretKey = getenv('JWT_SECRET') ?: 'default-access-secret';
            self::$refreshSecretKey = getenv('JWT_REFRESH_SECRET') ?: 'default-refresh-secret';
        }
    }

    public static function login($username, $password)
    {
        self::initKeys();
        $orm = new ORM();
        $user = $orm->selectWithJoin(
            baseTable: 'userauthentication u',
            joins: [
                ['table' => 'churchmember s', 'on' => 'u.MbrID = s.MbrCustomID']
            ],
            conditions: ['u.username' => ':username', 's.MbrMembershipStatus' => ':status'],
            params: [':username' => $username, ':status' => 'Active']
        )[0] ?? null;

        if (!$user) {
            throw new Exception("Error: We didn't find this Username");
        } elseif (!password_verify($password, $user['PasswordHash'])) {
            throw new Exception("Error: Password is Incorrect");
        }

        $refreshToken = self::generateRefreshToken($user);
        self::storeRefreshToken($user['MbrID'], $refreshToken);

        unset($user['PasswordHash'], $user['passwordText'], $user['MbrCustomID'], $user['CreatedAt'], $user['AuthUserID']);

        return [
            "type" => "ok",
            'access_token'  => self::generateToken($user, self::$secretKey, self::$accessTokenTTL),
            'refresh_token' => $refreshToken,
            'bio' => $user,
        ];
    }

    private static function generateToken($user, $secret, $ttl)
    {
        $issuedAt = time();
        $payload = [
            'user_id'   => $user['MbrID'],
            'user' => $user['Username'],
            'iat'   => $issuedAt,
            'exp'   => $issuedAt + $ttl
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    private static function generateRefreshToken($user)
    {
        return self::generateToken($user, self::$refreshSecretKey, self::$refreshTokenTTL);
    }

    private static function storeRefreshToken($userId, $token)
    {
        $orm = new ORM();
        $decoded = JWT::decode($token, new Key(self::$refreshSecretKey, 'HS256'));
        $expiresAt = date('Y-m-d H:i:s', $decoded->exp);

        $orm->insert('refresh_tokens', [
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => $expiresAt,
            'revoked'    => false
        ]);
    }

    public static function refreshAccessToken($refreshToken)
    {
        self::initKeys();

        $decoded = self::verify($refreshToken, self::$refreshSecretKey);
        if (!$decoded) {
            throw new Exception('Invalid refresh token');
        }

        $orm = new ORM();
        $tokenRecord = $orm->getWhere('refresh_tokens', ['token' => $refreshToken])[0] ?? null;

        if (!$tokenRecord || $tokenRecord['revoked'] || strtotime($tokenRecord['expires_at']) < time()) {
            throw new Exception('Refresh token is expired or revoked');
        }

        // Optionally revoke old refresh token and issue a new one:
        $orm->update('refresh_tokens', ['revoked' => true], ['id' => $tokenRecord['id']]);

        $user = [
            'id'    => $decoded->sub,
            'email' => $decoded->email,
            'role'  => $decoded->role
        ];

        // Issue new tokens
        $newAccessToken = self::generateToken($user, self::$secretKey, self::$accessTokenTTL);
        $newRefreshToken = self::generateRefreshToken($user);
        self::storeRefreshToken($user['id'], $newRefreshToken);

        return [
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRefreshToken
        ];
    }

    public static function verify($token, $secret = null)
    {
        self::initKeys();
        try {
            return JWT::decode($token, new Key($secret ?? self::$secretKey, 'HS256'));
        } catch (Exception $e) {
            return null;
        }
    }

    public static function requireRole($token, $roles = [])
    {
        $decoded = self::verify($token);
        if (!$decoded || !in_array($decoded->role, $roles)) {
            throw new Exception('Unauthorized');
        }
        return $decoded;
    }

    public static function getBearerToken()
    {
        // Get headers in a case-insensitive way
        $headers = getallheaders();
        $authorization = null;

        // Check for Authorization header (case-insensitive)
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authorization = $value;
                break;
            }
        }

        if (empty($authorization)) {
            error_log('Auth: No Authorization header found');
            return null;
        }

        // Match Bearer token with stricter regex
        if (preg_match('/^Bearer\s+([A-Za-z0-9\-_\.]+)/', $authorization, $matches)) {
            $token = trim($matches[1]);
            error_log('Auth: Extracted token: ' . substr($token, 0, 20) . '...');
            return $token;
        }

        error_log('Auth: Invalid Authorization header format: ' . $authorization);
        return null;
    }

    public static function revokeRefreshToken($refreshToken)
    {
        $orm = new ORM();
        $orm->update('refresh_tokens', ['revoked' => true], ['token' => $refreshToken]);
    }

    public static function cleanupExpiredRefreshTokens()
    {
        $orm = new ORM();
        $orm->delete('refresh_tokens', ['expires_at < NOW()']);
    }

    public static function logout($refreshToken)
    {
        self::revokeRefreshToken($refreshToken);
        return ['message' => 'Logged out successfully'];
    }

    public static function getUserFromToken($token)
    {
        $decoded = self::verify($token);
        if (!$decoded) {
            return null;
        }
        return [
            'id' => $decoded->user_id,
            'username' => $decoded->user,
            'iat' => $decoded->iat,
            'exp' => $decoded->exp
        ];
    }
}
