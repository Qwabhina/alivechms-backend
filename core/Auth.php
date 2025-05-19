<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';
require_once __DIR__ . '/Helpers.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    private static $accessTokenTTL = 1800; // 30 minutes
    private static $refreshTokenTTL = 86400; // 1 day
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
        try {
            $user = $orm->selectWithJoin(
                baseTable: 'userauthentication u',
                joins: [
                    ['table' => 'churchmember c', 'on' => 'u.MbrID = c.MbrID'],
                    ['table' => 'memberrole mr', 'on' => 'c.MbrID = mr.MbrID', 'type' => 'LEFT'],
                    ['table' => 'churchrole cr', 'on' => 'mr.ChurchRoleID = cr.RoleID', 'type' => 'LEFT']
                ],
                fields: ['u.*', 'c.*', 'cr.RoleName'],
                conditions: ['u.Username' => ':username', 'c.MbrMembershipStatus' => ':status'],
                params: [':username' => $username, ':status' => 'Active']
            )[0] ?? null;

            if (!$user) {
                Helpers::logError("Login failed: Username not found - $username");
                throw new Exception("Username not found: " . $user);
            }

            if (!password_verify($password, $user['PasswordHash'])) {
                Helpers::logError("Login failed: Incorrect password for $username");
                throw new Exception("Incorrect password");
            }

            $role = $orm->selectWithJoin(
                baseTable: 'memberrole mr',
                joins: [['table' => 'churchrole cr', 'on' => 'mr.ChurchRoleID = cr.RoleID']],
                fields: ['cr.RoleName'],
                conditions: ['mr.MbrID' => ':mbr_id'],
                params: [':mbr_id' => $user['MbrID']]
            );
            // $roleNames = array_column($roles, 'RoleName');

            $userData = [
                'MbrID' => $user['MbrID'],
                'Username' => $user['Username'],
                'Role' => $role
            ];

            $refreshToken = self::generateRefreshToken($userData);
            self::storeRefreshToken($user['MbrID'], $refreshToken);

            unset($user['PasswordHash'], $user['CreatedAt'], $user['AuthUserID']);

            return [
                'type' => 'ok',
                'access_token' => self::generateToken($userData, self::$secretKey, self::$accessTokenTTL),
                'refresh_token' => $refreshToken,
                'bio' => $user
            ];
        } catch (Exception $e) {
            Helpers::logError("Login error: " . $e->getMessage());
            throw $e;
        }
    }

    private static function generateToken($user, $secret, $ttl)
    {
        $issuedAt = time();
        $payload = [
            'user_id' => $user['MbrID'],
            'user' => $user['Username'],
            'role' => $user['Role'],
            'iat' => $issuedAt,
            'exp' => $issuedAt + $ttl
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
                'user_id' => $userId,
                'token' => $token,
                'expires_at' => $expiresAt,
                'revoked' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            Helpers::logError('Failed to store refresh token: ' . $e->getMessage());
            throw new Exception('Failed to store refresh token');
        }
    }

    public static function refreshAccessToken($refreshToken)
    {
        self::initKeys();
        try {
            $decoded = self::verify($refreshToken, self::$refreshSecretKey);
            if (!$decoded) {
                throw new Exception('Invalid refresh token');
            }

            $orm = new ORM();
            $tokenRecords = $orm->getWhere('refresh_tokens', [
                'token' => $refreshToken,
                'revoked' => 0
            ]);

            if (empty($tokenRecords)) {
                throw new Exception('Refresh token is invalid or revoked');
            }

            $tokenRecord = $tokenRecords[0];
            if (strtotime($tokenRecord['expires_at']) < time()) {
                $orm->update('refresh_tokens', ['revoked' => 1], ['id' => $tokenRecord['id']]);
                throw new Exception('Refresh token is expired');
            }

            $userRecords = $orm->selectWithJoin(
                baseTable: 'userauthentication u',
                joins: [
                    ['table' => 'churchmember c', 'on' => 'u.MbrID = c.MbrID'],
                    ['table' => 'memberrole mr', 'on' => 'c.MbrID = mr.MbrID', 'type' => 'LEFT'],
                    ['table' => 'churchrole cr', 'on' => 'mr.ChurchRoleID = cr.RoleID', 'type' => 'LEFT']
                ],
                fields: ['u.MbrID', 'u.Username', 'cr.RoleName'],
                conditions: ['u.MbrID' => ':mbr_id'],
                params: [':mbr_id' => $decoded['user_id']]
            );

            if (empty($userRecords)) {
                throw new Exception('User not found');
            }

            $roles = array_column($userRecords, 'RoleName');
            $userData = [
                'MbrID' => $userRecords[0]['MbrID'],
                'Username' => $userRecords[0]['Username'],
                'Roles' => array_unique($roles)
            ];

            $orm->update('refresh_tokens', ['revoked' => 1], ['id' => $tokenRecord['id']]);
            $newAccessToken = self::generateToken($userData, self::$secretKey, self::$accessTokenTTL);
            $newRefreshToken = self::generateRefreshToken($userData);
            self::storeRefreshToken($userData['MbrID'], $newRefreshToken);

            return [
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken
            ];
        } catch (Exception $e) {
            Helpers::logError('Refresh token error: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function verify($token, $secret = null)
    {
        self::initKeys();
        $secret = $secret ?? self::$secretKey;
        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return json_decode(json_encode($decoded), true);
        } catch (Exception $e) {
            Helpers::logError('Token verification failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function checkPermission($token, $requiredPermission)
    {
        self::initKeys();
        self::verify($token) ?: throw new Exception('Unauthorized: Invalid token');

        $decoded = JWT::decode($token, new Key(self::$secretKey, 'HS256'));
        if (!$decoded) {
            throw new Exception('Unauthorized: Token malformed or missing roles');
        }
        $decoded_array = json_decode(json_encode($decoded), true);

        $userId = $decoded_array['user_id'];

        $orm = new ORM();
        $results = $orm->selectWithJoin(
            baseTable: 'userauthentication u',
            joins: [
                ['table' => 'memberrole mr',        'on' => 'u.MbrID = mr.MbrID'],
                ['table' => 'churchrole cr',        'on' => 'mr.ChurchRoleID = cr.RoleID'],
                ['table' => 'rolepermission rp',    'on' => 'cr.RoleID = rp.ChurchRoleID'],
                ['table' => 'permission p',         'on' => 'rp.PermissionID = p.PermissionID']
            ],
            fields: ['p.PermissionName'],
            conditions: ['u.MbrID' => ':username'],
            params: [':username' => $userId]
        );

        // Extract permission names
        $permissions = array_column($results, 'PermissionName');

        if (!in_array($requiredPermission, $permissions)) {
            Helpers::logError('Forbidden: Insufficient permissions for user ' . $userId);
            Helpers::sendError('Forbidden: Insufficient permissions', 403);
        }
    }


    public static function getBearerToken()
    {
        Helpers::addCorsHeaders();
        $headers = getallheaders();
        $authorization = null;

        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authorization = $value;
                break;
            }
        }

        if (empty($authorization)) {
            Helpers::logError('No Authorization header found');
            return null;
        }

        if (preg_match('/^Bearer\s+([A-Za-z0-9\-_\.]+)/', $authorization, $matches)) {
            return trim($matches[1]);
        }

        Helpers::logError('Invalid Authorization header format: ' . $authorization);
        return null;
    }

    public static function revokeRefreshToken($refreshToken)
    {
        $orm = new ORM();
        $orm->update('refresh_tokens', ['revoked' => 1], ['token' => $refreshToken]);
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
            'id' => $decoded['user_id'],
            'username' => $decoded['user'],
            'roles' => $decoded['roles'],
            'iat' => $decoded['iat'],
            'exp' => $decoded['exp']
        ];
    }
}
?>