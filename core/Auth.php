<?php

/**
 * Authentication & Authorization Manager
 *
 * Handles JWT generation, verification, refresh-token lifecycle,
 * permission checks, secure logout, and user context retrieval.
 *
 * All tokens use separate secrets and HS256 algorithm.
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

    private static ?string $accessSecret  = null;
    private static ?string $refreshSecret = null;

    /**
     * Initialise JWT secrets from environment
     *
     * @return void
     * @throws Exception If secrets are missing or empty
     */
    private static function initSecrets(): void
    {
        if (self::$accessSecret !== null) {
            return; // Already initialised
        }

        $access  = $_ENV['JWT_SECRET']        ?? '';
        $refresh = $_ENV['JWT_REFRESH_SECRET'] ?? '';

        if ($access === '' || $refresh === '') {
            throw new Exception('JWT secrets are not configured in .env');
        }

        self::$accessSecret  = $access;
        self::$refreshSecret = $refresh;
    }

    /**
     * Generate a JWT token
     *
     * @param array  $payload User payload
     * @param string $secret  Secret key to use
     * @param int    $ttl     Time-to-live in seconds
     * @return string Encoded JWT
     */
    private static function generateToken(array $payload, string $secret, int $ttl): string
    {
        self::initSecrets();

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttl;

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
        self::initSecrets();

        $payload = [
            'user_id'  => $user['MbrID'],
            'username' => $user['Username'],
            'role'     => $user['Role'] ?? [],
        ];

        return self::generateToken($payload, self::$accessSecret, self::ACCESS_TOKEN_TTL);
    }

    /**
     * Generate refresh token (24 hours)
     *
     * @param array $user User data (MbrID, Username)
     * @return string Refresh token
     */
    public static function generateRefreshToken(array $user): string
    {
        self::initSecrets();

        $payload = [
            'user_id'  => $user['MbrID'],
            'username' => $user['Username'],
        ];

        return self::generateToken($payload, self::$refreshSecret, self::REFRESH_TOKEN_TTL);
    }

    /**
     * Store refresh token in database
     *
     * @param int    $userId      Member ID
     * @param string $refreshToken Refresh token string
     * @return void
     */
    public static function storeRefreshToken(int $userId, string $refreshToken): void
    {
        $orm = new ORM();

        $decoded   = JWT::decode($refreshToken, new Key(self::$refreshSecret, 'HS256'));
        $expiresAt = date('Y-m-d H:i:s', $decoded->exp);

        $orm->insert('refresh_tokens', [
            'user_id'     => $userId,
            'token'       => $refreshToken,
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
        self::initSecrets();
        $secret ??= self::$accessSecret;

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (ExpiredException | BeforeValidException | SignatureInvalidException | Exception $e) {
            Helpers::logError('JWT verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract Bearer token from Authorization header
     *
     * @return string|null Token or null if missing/invalid
     */
    public static function getBearerToken(): ?string
    {
        $headers = getallheaders();
        $header  = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get current authenticated user ID
     *
     * @param string|null $token Optional token (uses request header if null)
     * @return int User ID (MbrID)
     * @throws Exception If token is invalid or missing
     */
    public static function getCurrentUserId(?string $token = null): int
    {
        $token ??= self::getBearerToken();

        if (!$token) {
            Helpers::sendFeedback('Authentication token missing', 401);
        }

        $payload = self::verify($token);
        if (!$payload || !isset($payload['user_id'])) {
            Helpers::sendFeedback('Invalid or expired token', 401);
        }

        return (int)$payload['user_id'];
    }

    /**
     * Get branch ID of the currently authenticated user
     *
     * @param int|null $userId Optional user ID (uses current user if null)
     * @return int BranchID
     * @throws Exception If user or branch not found
     */
    public static function getUserBranchId(?int $userId = null): int
    {
        $userId ??= self::getCurrentUserId();
        $orm = new ORM();

        $user = $orm->getWhere('churchmember', ['MbrID' => $userId, 'Deleted' => 0]);
        if (empty($user) || empty($user[0]['BranchID'])) {
            Helpers::sendFeedback('User or branch not found', 404);
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
    public static function checkPermission(string $token, string $requiredPermission): void
    {
        $payload = self::verify($token);
        if (!$payload || !isset($payload['user_id'])) {
            Helpers::sendFeedback('Invalid token', 401);
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
            params: [':user_id' => $payload['user_id']]
        );

        $permissions = array_column($results, 'PermissionName');

        if (!in_array($requiredPermission, $permissions, true)) {
            Helpers::logError("Permission denied: user {$payload['user_id']} → $requiredPermission");
            Helpers::sendFeedback('Forbidden: Insufficient permissions', 403);
        }
    }

    /**
     * Perform user login and issue tokens
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
            Helpers::sendFeedback('Invalid credentials', 401);
        }

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

        unset($user['PasswordHash']);

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
        if ($refreshToken === '') {
            Helpers::sendFeedback('Refresh token required', 400);
        }

        $payload = self::verify($refreshToken, self::$refreshSecret);
        if (!$payload) {
            Helpers::sendFeedback('Invalid or expired refresh token', 401);
        }

        $orm = new ORM();
        $stored = $orm->getWhere('refresh_tokens', ['token' => $refreshToken, 'revoked' => 0]);

        if (empty($stored)) {
            Helpers::sendFeedback('Refresh token revoked or invalid', 401);
        }

        // Revoke old refresh token
        $orm->update('refresh_tokens', ['revoked' => 1], ['id' => $stored[0]['id']]);

        // Regenerate roles (in case they changed)
        $roles = $orm->runQuery(
            "SELECT cr.RoleName FROM memberrole mr 
             JOIN churchrole cr ON mr.ChurchRoleID = cr.RoleID 
             WHERE mr.MbrID = :id",
            [':id' => $payload['user_id']]
        );

        $userData = [
            'MbrID'    => $payload['user_id'],
            'Username' => $payload['username'],
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
     * @param string $refreshToken Token to revoke
     * @return void
     */
    public static function logout(string $refreshToken): void
    {
        if ($refreshToken === '') {
            Helpers::sendFeedback('Refresh token required', 400);
        }

        $orm = new ORM();
        $orm->update('refresh_tokens', ['revoked' => 1], ['token' => $refreshToken]);

        Helpers::sendFeedback('Logged out successfully', 200, 'success');
    }
}