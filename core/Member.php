<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';

use Firebase\JWT\JWT;

class Member
{
    public static function register($data)
    {
        $orm = new ORM();
        $username = $data['username'];
        $password = password_hash($data['password'], PASSWORD_BCRYPT);
        $email = $data['email'];

        // Check if username or email already exists
        $existingUser = $orm->getWhere('userauthentication', ['username' => $username, 'email' => $email]);
        if ($existingUser) {
            throw new Exception("Username or email already exists.");
        }

        // Insert new user
        $userId = $orm->insert('userauthentication', [
            'username' => $username,
            'password' => $password,
            'email' => $email,
        ]);

        return [
            'status' => 'success',
            'user_id' => $userId,
        ];
    }
}
