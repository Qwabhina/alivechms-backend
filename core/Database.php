<?php

/**
 * Database Connection Manager
 *
 * Singleton class responsible for establishing and providing a secure PDO connection
 * to the MySQL database using configuration from environment variables.
 *
 * Features:
 * - Singleton pattern to ensure only one connection per request
 * - Persistent connections disabled by default (recommended for most APIs)
 * - Strict PDO options for security and error handling
 * - Automatic reconnection attempt on failure (basic resilience)
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-19
 */

class Database
{
    /**
     * The single instance of the Database class
     *
     * @var Database|null
     */
    private static ?Database $instance = null;

    /**
     * PDO database connection instance
     *
     * @var PDO
     */
    private PDO $connection;

    /**
     * Private constructor to prevent direct instantiation
     *
     * Initializes the PDO connection with secure defaults:
     * - Exception error mode
     * - Associative fetch mode
     * - Emulated prepares disabled (real prepared statements)
     * - UTF8MB4 charset
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
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,       // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,              // Always return associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                         // Use real prepared statements
            PDO::ATTR_PERSISTENT         => false,                         // Do not use persistent connections
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_SSL_CA       => null,                          // Enable in production with valid cert
        ];

        $maxRetries = 3;
        $retryDelay = 1; // second

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->connection = new PDO($dsn, $user, $pass, $options);
                // Test the connection
                $this->connection->query('SELECT 1');
                return; // Success → exit constructor
            } catch (PDOException $e) {
                $errorMsg = "Database connection attempt $attempt failed: " . $e->getMessage();

                if ($attempt === $maxRetries) {
                    Helpers::logError($errorMsg);
                    Helpers::sendFeedback('Database connection failed. Please try again later.', 503);
                }

                // Wait before retry (avoid hammering)
                sleep($retryDelay);
            }
        }
    }

    /**
     * Get the singleton instance of the Database
     *
     * @return Database The single instance
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
     * @return PDO The PDO instance
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Prevent cloning of the singleton instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the singleton instance
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}