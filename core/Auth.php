<?php

/**
 * Authentication & Authorization Manager
 *
 * Handles JWT-based authentication, token generation, verification,
 * refresh tokens, permission checks, and secure logout.

 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\SignatureInvalidException;

class Auth
{
    private const ACCESS_TOKEN_TTL  = 1800;  // 30 minutes
    private const REFRESH_TOKEN_TTL = 86400; // 24 hours

    private static ?string $secretKey  = null;
    private static ?string $refreshSecretKey = null;

    /**
     * Initialize JWT secrets from environment variables
     *
     * @return void
     * @throws Exception If secrets are missing or empty
     */
    private static function initKeys(): void
    {
        if (self::$secretKey !== null) {
            return;
        }

        if (!isset($_ENV['JWT_SECRET']) || !isset($_ENV['JWT_REFRESH_SECRET'])) {
            throw new Exception('JWT secrets not configured. Ensure .env is loaded and contains JWT_SECRET and JWT_REFRESH_SECRET.');
        }

        if ($_ENV['JWT_SECRET'] === '' || $_ENV['JWT_REFRESH_SECRET'] === '') {
            throw new Exception('JWT secrets cannot be empty strings.');
        }

        self::$secretKey        = $_ENV['JWT_SECRET'];
        self::$refreshSecretKey = $_ENV['JWT_REFRESH_SECRET'];
    }

    /**
     * Generate a JWT token
     *
     * @param array  $payload User payload
     * @param string $secret  Secret key to use
     * @param int    $ttl     Time-to-live in seconds
     * @return string Encoded JWT
     */
    private static function generateToken(array $user, string $secret, int $ttl): string
    {
        self::initKeys();

        $issuedAt  = time();
        $expireAt  = $issuedAt + $ttl;

        $payload = [
            'iat'      => $issuedAt,
            'exp'      => $expireAt,
            'user_id'  => $user['MbrID'],
            'username' => $user['Username'],
            'role'     => $user['Role'] ?? [],
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Generate access token (30 minutes)
     *
     * @param array $user User data (MbrID, Username, Role[])
     * @return string Access token
     */
    public static function generateAccessToken(array $user): string
    {
        self::initKeys();
        return self::generateToken($user, self::$secretKey, self::ACCESS_TOKEN_TTL);
    }

    /**
     * Generate refresh token (24 hours)
     *
     * @param array $user User data (MbrID, Username)
     * @return string Refresh token
     */
    public static function generateRefreshToken(array $user): string
    {
        self::initKeys();
        return self::generateToken($user, self::$refreshSecretKey, self::REFRESH_TOKEN_TTL);
    }

    /**
     * Store refresh token in database
     *
     * @param int    $userId      Member ID
     * @param string $refreshToken Refresh token string
     * @return void
     */
    public static function storeRefreshToken(int $userId, string $token): void
    {
        $orm = new ORM();

        $decoded   = JWT::decode($token, new Key(self::$refreshSecretKey, 'HS256'));
        $expiresAt = date('Y-m-d H:i:s', $decoded->exp);

        $orm->insert('refresh_tokens', [
            'user_id'     => $userId,
            'token'       => $token,
            'expires_at'  => $expiresAt,
            'revoked'     => 0,
            'created_at'  => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Verify and decode a JWT token
     *
     * @param string      $token  JWT string
     * @param string|null $secret Override secret (null = access secret)
     * @return array|false Decoded payload or false on failure
     */
    public static function verify(string $token, ?string $secret = null)
    {
        self::initKeys();
        $secret ??= self::$secretKey;

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            Helpers::logError("Token expired: " . $e->getMessage());
        } catch (BeforeValidException | SignatureInvalidException $e) {
            Helpers::logError("Invalid token signature: " . $e->getMessage());
        } catch (Exception $e) {
            Helpers::logError("Token verification failed: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Extract Bearer token from Authorization header
     *
     * @return string|null Token or null if missing/invalid
     */
    public static function getBearerToken(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if ($authHeader === null) {
            return null;
        }

        if (preg_match('/Bearer\s+([A-Za-z0-9._~+\/-]+=*)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        Helpers::logError('Invalid Authorization header format: ' . $authHeader);
        return null;
    }

    /**
     * Get current authenticated user ID from token
     *
     * @return int User ID (MbrID)
     * @throws Exception If token is invalid or missing
     */
    public static function getCurrentUserId(): int
    {
        $token = self::getBearerToken();

        if (!$token) {
            throw new Exception('No authentication token provided');
        }

        $decoded = self::verify($token);

        if (!$decoded || !isset($decoded['user_id'])) {
            throw new Exception('Invalid or expired token');
        }

        return (int)$decoded['user_id'];
    }

    /**
     * Get branch ID of the currently authenticated user
     *
     * @return int BranchID of the Authenticated User
     * @throws Exception If user or branch not found
     */
    public static function getUserBranchId(): int
    {
        $userId = self::getCurrentUserId();
        $orm = new ORM();

        $user = $orm->getWhere('churchmember', ['MbrID' => $userId, 'Deleted' => 0]);

        if (empty($user)) {
            throw new Exception('User not found');
        }

        if (empty($user[0]['BranchID'])) {
            throw new Exception('User has no branch assigned');
        }

        return (int)$user[0]['BranchID'];
    }

    /**
     * Check if current user has required permission
     *
     * @param string      $token             Access token
     * @param string      $requiredPermission Permission name
     * @return void
     * @throws Exception On insufficient permission
     */
    public static function checkPermission(string $requiredPermission): void
    {
        $token = self::getBearerToken();

        if (!$token) {
            Helpers::sendFeedback('Unauthorized: No authentication token', 401);
        }

        $decoded = self::verify($token);

        if (!$decoded || !isset($decoded['user_id'])) {
            Helpers::sendFeedback('Unauthorized: Invalid or expired token', 401);
        }

        $orm = new ORM();

        $results = $orm->selectWithJoin(
            baseTable: 'userauthentication u',
            joins: [
                ['table' => 'memberrole mr',       'on' => 'u.MbrID = mr.MbrID'],
                ['table' => 'churchrole cr',       'on' => 'mr.ChurchRoleID = cr.RoleID'],
                ['table' => 'rolepermission rp',   'on' => 'cr.RoleID = rp.ChurchRoleID'],
                ['table' => 'permission p',        'on' => 'rp.PermissionID = p.PermissionID']
            ],
            fields: ['p.PermissionName'],
            conditions: ['u.MbrID' => ':user_id'],
            params: [':user_id' => $decoded['user_id']]
        );

        $permissions = array_column($results, 'PermissionName');

        if (!in_array($requiredPermission, $permissions, true)) {
            Helpers::logError("Forbidden access attempt by user {$decoded['user_id']} for permission: $requiredPermission");
            Helpers::sendFeedback('Forbidden: Insufficient permissions', 403);
        }
    }

    /**
     * Perform user login and issue authentication tokens
     *
     * @param string $username Username
     * @param string $password Plain-text password
     * @return array Tokens and user data
     */
    public static function login(string $username, string $password): array
    {
        $orm = new ORM();

        $user = $orm->selectWithJoin(
            baseTable: 'userauthentication u',
            joins: [
                ['table' => 'churchmember c', 'on' => 'u.MbrID = c.MbrID'],
                ['table' => 'memberrole mr', 'on' => 'c.MbrID = mr.MbrID', 'type' => 'LEFT'],
                ['table' => 'churchrole cr', 'on' => 'mr.ChurchRoleID = cr.RoleID', 'type' => 'LEFT']
            ],
            fields: ['u.MbrID', 'u.Username', 'u.PasswordHash', 'c.*', 'cr.RoleName'],
            conditions: ['u.Username' => ':username', 'c.MbrMembershipStatus' => ':status'],
            params: [':username' => $username, ':status' => 'Active']
        )[0] ?? null;

        if (!$user || !password_verify($password, $user['PasswordHash'])) {
            Helpers::logError("Failed login attempt for username: $username");
            throw new Exception('Invalid credentials');
        }

        // Get all roles of user
        $roles = $orm->runQuery(
            "SELECT cr.RoleName FROM memberrole mr 
             JOIN churchrole cr ON mr.ChurchRoleID = cr.RoleID 
             WHERE mr.MbrID = :id",
            [':id' => $user['MbrID']]
        );

        $roleNames = array_column($roles, 'RoleName');

        $userData = [
            'MbrID'    => $user['MbrID'],
            'Username' => $user['Username'],
            'Role'     => $roleNames
        ];

        $refreshToken = self::generateRefreshToken($userData);
        self::storeRefreshToken($user['MbrID'], $refreshToken);

        // Update last login
        $orm->update(
            'userauthentication',
            ['LastLoginAt' => date('Y-m-d H:i:s')],
            ['MbrID' => $user['MbrID']]
        );

        unset($user['PasswordHash'], $user['CreatedAt'], $user['AuthUserID']);

        return [
            'status'        => 'success',
            'access_token'  => self::generateAccessToken($userData),
            'refresh_token' => $refreshToken,
            'user'          => $user
        ];
    }

    /**
     * Refresh access token using a valid refresh token
     *
     * @param string $refreshToken Valid refresh token
     * @return array New tokens
     */
    public static function refreshAccessToken(string $refreshToken): array
    {
        if (empty($refreshToken)) {
            throw new Exception('Refresh token required');
        }

        $decoded = self::verify($refreshToken, self::$refreshSecretKey);

        if (!$decoded) {
            throw new Exception('Invalid or expired refresh token');
        }

        $orm = new ORM();
        $stored = $orm->getWhere('refresh_tokens', [
            'token'   => $refreshToken,
            'revoked' => 0
        ]);

        if (empty($stored)) {
            throw new Exception('Refresh token revoked or invalid');
        }

        $tokenRecord = $stored[0];

        if (strtotime($tokenRecord['expires_at']) < time()) {
            $orm->update('refresh_tokens', ['revoked' => 1], ['id' => $tokenRecord['id']]);
            throw new Exception('Refresh token expired');
        }

        // Revoke old refresh token
        $orm->update('refresh_tokens', ['revoked' => 1], ['id' => $tokenRecord['id']]);

        // Fetch fresh user roles
        $roles = $orm->runQuery(
            "SELECT cr.RoleName FROM memberrole mr 
             JOIN churchrole cr ON mr.ChurchRoleID = cr.RoleID 
             WHERE mr.MbrID = :id",
            [':id' => $decoded['user_id']]
        );

        $userData = [
            'MbrID'    => $decoded['user_id'],
            'Username' => $decoded['username'],
            'Role'     => array_column($roles, 'RoleName')
        ];

        $newRefreshToken = self::generateRefreshToken($userData);
        self::storeRefreshToken($userData['MbrID'], $newRefreshToken);

        return [
            'access_token'  => self::generateAccessToken($userData),
            'refresh_token' => $newRefreshToken
        ];
    }

    /**
     * Logout – revoke refresh token
     *
     * @param string $refreshToken Valid token to revoke
     * @return void
     */
    public static function logout(string $refreshToken): void
    {
        if (empty($refreshToken)) {
            throw new Exception('Refresh token required');
        }

        $orm = new ORM();
        $orm->update('refresh_tokens', ['revoked' => 1], ['token' => $refreshToken]);
    }

    /**
     * Clean up expired tokens (run via cron)
     */
    public static function cleanupExpiredTokens(): int
    {
        $orm = new ORM();

        // Delete tokens expired more than 7 days ago
        $result = $orm->runQuery(
            "DELETE FROM refresh_tokens 
             WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
            []
        );

        return $result ? count($result) : 0;
    }
}