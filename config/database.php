<?php
/**
 * MySQL / PlanetScale Database Connection (PDO)
 *
 * Environment Variables (set in Render Dashboard → Environment):
 * ┌──────────────────┬────────────────────────────────────────────────┐
 * │ Variable         │ Example Value                                  │
 * ├──────────────────┼────────────────────────────────────────────────┤
 * │ MYSQL_HOST       │ db.example.com  (or PlanetScale host)          │
 * │ MYSQL_DB_NAME    │ lab_coffee                                     │
 * │ MYSQL_USERNAME   │ root                                           │
 * │ MYSQL_PASSWORD   │ supersecretpassword                            │
 * │ MYSQL_PORT       │ 3306  (optional, defaults to 3306)             │
 * └──────────────────┴────────────────────────────────────────────────┘
 *
 * Local development fallback: localhost / root / (empty password)
 */

namespace Config;

use PDO;
use PDOException;

class Database {
    // Read credentials from environment variables with sensible local fallbacks
    private string $host;
    private string $db_name;
    private string $username;
    private string $password;
    private int    $port;
    private ?PDO   $conn = null;

    public function __construct() {
        $this->host     = getenv('MYSQL_HOST')     ?: 'localhost';
        $this->db_name  = getenv('MYSQL_DB_NAME')  ?: 'lab_coffee';
        $this->username = getenv('MYSQL_USERNAME')  ?: 'root';
        $this->password = getenv('MYSQL_PASSWORD')  ?: '';
        $this->port     = (int)(getenv('MYSQL_PORT') ?: 3306);
    }

    /**
     * Get the database connection using PDO.
     *
     * @return PDO
     */
    public function getConnection(): PDO {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->host,
                $this->port,
                $this->db_name
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // SSL for managed cloud databases (PlanetScale, AWS RDS, etc.)
                // Uncomment the line below if your cloud DB requires SSL:
                // PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

        } catch (PDOException $exception) {
            error_log("MySQL Connection Error: " . $exception->getMessage());

            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please contact your system administrator.'
            ]);
            exit;
        }

        return $this->conn;
    }
}
