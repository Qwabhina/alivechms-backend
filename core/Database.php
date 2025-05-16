<?php
class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        // You can load these from a config file or environment
        $host = $_ENV['DB_HOST'] ?: 'localhost';
        $dbname = $_ENV['DB_NAME'] ?: 'alivechms';
        $user = $_ENV['DB_USER'] ?: 'root';
        $pass = $_ENV['DB_PASS'] ?: '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

        try {
            $this->connection = new PDO($dsn, $user, $pass);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Log error in production, don't expose details
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
