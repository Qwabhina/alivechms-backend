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
            self::$secretKey = $_ENV['JWT_SECRET'] ?: 'default-access-secret';
            self::$refreshSecretKey = $_ENV['JWT_REFRESH_SECRET'] ?: 'default-refresh-secret';
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
            'user_id' => $user['MbrID'],
            'user'    => $user['Username'],
            'iat'     => $issuedAt,
            'exp'     => $issuedAt + $ttl
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    private static function generateRefreshToken($user)
    {
        return self::generateToken($user, self::$refreshSecretKey, self::$refreshTokenTTL);
    }

    private static function storeRefreshToken($userId, $token)
    {
        try {
            self::initKeys();
            $orm = new ORM();
            $decoded = JWT::decode($token, new Key(self::$refreshSecretKey, 'HS256'));
            $expiresAt = date('Y-m-d H:i:s', $decoded->exp);

            $orm->insert('refresh_tokens', [
                'user_id'    => $userId,
                'token'      => $token,
                'expires_at' => $expiresAt,
                'revoked'    => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log('Auth: Failed to store refresh token: ' . $e->getMessage());
            throw new Exception('Failed to store refresh token');
        }
    }

    public static function refreshAccessToken($refreshToken)
    {
        self::initKeys();
        if (self::$refreshSecretKey === null) {
            error_log('Auth: Refresh secret key is not set');
            throw new Exception('Server configuration error');
        }

        try {
            $decoded = self::verify($refreshToken, self::$refreshSecretKey);
            if (!$decoded || !is_array($decoded)) {
                error_log('Auth: Invalid refresh token or not an array: ' . print_r($decoded, true));
                throw new Exception('Invalid refresh token');
            }

            error_log('Auth: Decoded refresh token: ' . json_encode($decoded));

            $orm = new ORM();
            $tokenRecords = $orm->getWhere('refresh_tokens', [
                'token'   => $refreshToken,
                'revoked' => 0
            ]);

            if (empty($tokenRecords)) {
                error_log('Auth: Refresh token not found or revoked');
                throw new Exception('Refresh token is invalid or revoked');
            }

            $tokenRecord = $tokenRecords[0];
            if (strtotime($tokenRecord['expires_at']) < time()) {
                error_log('Auth: Refresh token expired: ' . $tokenRecord['expires_at']);
                $orm->update('refresh_tokens', ['revoked' => 1], ['id' => $tokenRecord['id']]);
                throw new Exception('Refresh token is expired');
            }

            $userRecords = $orm->getWhere('userauthentication', ['MbrID' => $decoded['user_id']]);
            if (empty($userRecords)) {
                error_log('Auth: User not found for MbrID: ' . $decoded['user_id']);
                throw new Exception('User not found');
            }

            $user = $userRecords[0];
            $orm->update('refresh_tokens', ['revoked' => 1], ['id' => $tokenRecord['id']]);
            $newAccessToken = self::generateToken($user, self::$secretKey, self::$accessTokenTTL);
            $newRefreshToken = self::generateRefreshToken($user);
            self::storeRefreshToken($user['MbrID'], $newRefreshToken);

            return [
                'access_token'  => $newAccessToken,
                'refresh_token' => $newRefreshToken
            ];
        } catch (Exception $e) {
            error_log('Auth: refreshAccessToken failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function verify($token, $secret = null)
    {
        self::initKeys();
        $secret = $secret ?? self::$secretKey;
        if ($secret === null) {
            error_log('Auth: Secret key is not set');
            throw new Exception('Server configuration error');
        }

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return json_decode(json_encode($decoded), true);
        } catch (Exception $e) {
            error_log('Auth: Token verification failed: ' . $e->getMessage());
            return false;
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
