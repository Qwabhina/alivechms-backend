<?php

/** Auth class for handling user authentication and authorization.
 * This class provides methods for user login, token generation, token verification,
 * permission checking, and token management.
 * @package Auth
 * @version 1.0
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
class Auth
{
    /**
     * @var int $accessTokenTTL Time to live for access tokens in seconds.
     * @var int $refreshTokenTTL Time to live for refresh tokens in seconds.
     * @var string $secretKey Secret key for signing access tokens.
     * @var string $refreshSecretKey Secret key for signing refresh tokens.
     */
    private static $accessTokenTTL = 1800; // 30 minutes
    private static $refreshTokenTTL = 86400; // 1 day
    private static $secretKey;
    private static $refreshSecretKey;
    /**
     * Initialize the secret keys from environment variables or set defaults.
     */
    private static function initKeys()
    {
        if (!self::$secretKey) {
            self::$secretKey = $_ENV['JWT_SECRET'] ?: 'default-access-secret';
            self::$refreshSecretKey = $_ENV['JWT_REFRESH_SECRET'] ?: 'default-refresh-secret';
        }
    }
    /**
     * Generate a JWT token for the user.
     * @param array $user User data including ID, username, and role.
     * @param string $secret Secret key to sign the token.
     * @param int $ttl Time to live for the token in seconds.
     * @return string The generated JWT token.
     */
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
    /** Generate a refresh token for the user.
     * @param array $user User data including ID and username.
     * @return string The generated refresh token.
     */
    private static function generateRefreshToken($user)
    {
        return self::generateToken($user, self::$refreshSecretKey, self::$refreshTokenTTL);
    }
    /**
     * Store the refresh token in the database.
     * @param int $userId The user ID to associate with the refresh token.
     * @param string $token The refresh token to store.
     * @throws Exception If storing the token fails.
     */
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
            Helpers::sendFeedback('Failed to store refresh token', 500);
        }
    }
    /**
     * Refresh the access token using the provided refresh token.
     * @param string $refreshToken The refresh token to use for generating a new access token.
     * @return array An array containing the new access token and refresh token.
     * @throws Exception If the refresh token is invalid or expired.
     */
    public static function refreshAccessToken($refreshToken)
    {
        self::initKeys();
        try {
            $decoded = self::verify($refreshToken, self::$refreshSecretKey);
            if (!$decoded)  Helpers::sendFeedback('Error: Invalid refresh token', 401);

            $orm = new ORM();
            $tokenRecords = $orm->getWhere('refresh_tokens', [
                'token' => $refreshToken,
                'revoked' => 0
            ]);

            if (empty($tokenRecords)) Helpers::sendFeedback('Refresh token is invalid or revoked', 401);

            $tokenRecord = $tokenRecords[0];
            if (strtotime($tokenRecord['expires_at']) < time()) {
                $orm->update('refresh_tokens', ['revoked' => 1], ['id' => $tokenRecord['id']]);
                Helpers::sendFeedback('Refresh token is expired', 400);
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

            if (empty($userRecords)) Helpers::sendFeedback('User not found', 404);

            $roles = array_column($userRecords, 'RoleName');
            $userData = [
                'MbrID' => $userRecords[0]['MbrID'],
                'Username' => $userRecords[0]['Username'],
                'Role' => array_unique($roles)
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
            Helpers::sendFeedback('Refresh token error', 400);
        }
    }
    /**
     * Verify the JWT token and return the decoded payload.
     * @param string $token The JWT token to verify.
     * @param string|null $secret The secret key to use for verification. If null, uses the default secret key.
     * @return array|false The decoded payload or false if verification fails.
     */
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
    /**
     * Check if the user has the required permission.
     * @param string $token The JWT token.
     * @param string $requiredPermission The permission to check.
     * @throws Exception If the token is invalid or the user lacks the required permission.
     */
    public static function checkPermission($token, $requiredPermission)
    {
        self::initKeys();
        self::verify($token) ?: Helpers::sendFeedback('Unauthorized: Invalid token', 401);

        $decoded = JWT::decode($token, new Key(self::$secretKey, 'HS256'));
        if (!$decoded) Helpers::sendFeedback('Unauthorized: Token malformed or missing roles', 401);

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
            Helpers::sendFeedback('Forbidden: Insufficient permissions', 403);
        }
    }
    /**
     * Get the Bearer token from the Authorization header.
     * @return string|null The Bearer token or null if not found.
     */
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

        if (preg_match('/^Bearer\s+([A-Za-z0-9\-_\.]+)/', $authorization, $matches)) return trim($matches[1]);

        Helpers::logError('Invalid Authorization header format: ' . $authorization);
        return null;
    }
    /**
     * Revoke the refresh token by marking it as revoked in the database.
     * @param string $refreshToken The refresh token to revoke.
     */
    public static function  revokeRefreshToken($refreshToken)
    {
        $orm = new ORM();
        $orm->update('refresh_tokens', ['revoked' => 1], ['token' => $refreshToken]);
    }
    /**
     * Get user information from the JWT token.
     * @param string $token The JWT token.
     * @return array|null An array containing user information or null if token is invalid.
     */
    public static function getUserFromToken($token)
    {
        $decoded = self::verify($token);
        if (!$decoded) return null;

        return [
            'id' => $decoded['user_id'],
            'username' => $decoded['user'],
            'roles' => $decoded['roles'],
            'iat' => $decoded['iat'],
            'exp' => $decoded['exp']
        ];
    }
    /**
     * Login method to authenticate user and generate access and refresh tokens.
     * @param string $username The username of the user.
     * @param string $password The password of the user.
     * @return array An array containing the access token, refresh token, and user bio.
     * @throws Exception If login fails or user not found.
     */
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

            if (!$user || !password_verify($password, $user['PasswordHash'])) Helpers::sendFeedback("Incorrect login details");

            $role = $orm->selectWithJoin(
                baseTable: 'memberrole mr',
                joins: [['table' => 'churchrole cr', 'on' => 'mr.ChurchRoleID = cr.RoleID']],
                fields: ['cr.RoleName'],
                conditions: ['mr.MbrID' => ':mbr_id'],
                params: [':mbr_id' => $user['MbrID']]
            );

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
            Helpers::sendFeedback("Login failed");
        }
    }
    /**
     * Logout method to revoke the refresh token.
     * @param string $refreshToken The refresh token to revoke.
     * @return array An array containing a success message.
     */
    public static function logout($refreshToken)
    {
        self::revokeRefreshToken($refreshToken);
        Helpers::sendFeedback('Logged out successfully', 200, 'success');
    }
}
?>