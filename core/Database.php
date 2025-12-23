<?php

/**
 * Database Connection Manager
 *
 * Secure singleton PDO connection with automatic reconnection,
 * strict security options, and UTF8MB4 support.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Database
{
    private static ?self $instance = null;
    private PDO $connection;

    /**
     * Private constructor – establishes secure PDO connection
     *
     * @return void
     * @throws PDOException On connection failure after retries
     */
    private function __construct()
    {
        $host     = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbname   = $_ENV['DB_NAME'] ?? 'alivechms';
        $user     = $_ENV['DB_USER'] ?? 'root';
        $pass     = $_ENV['DB_PASS'] ?? '';
        $charset  = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE utf8mb4_unicode_ci",
        ];

        $maxRetries = 3;
        $delay      = 1; // second

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->connection = new PDO($dsn, $user, $pass, $options);
                $this->connection->query('SELECT 1');
                return;
            } catch (PDOException $e) {
                $msg = "Database connection attempt $attempt failed: " . $e->getMessage();

                if ($attempt === $maxRetries) {
                    Helpers::logError($msg);
                    Helpers::sendFeedback('Service temporarily unavailable', 503);
                }

                sleep($delay);
            }
        }
    }

    /**
     * Get the singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get the active PDO connection
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Prevent cloning of the singleton
     *
     * @return void
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the singleton
     *
     * @return void
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton Database');
    }
}