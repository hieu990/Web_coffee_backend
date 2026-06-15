<?php
/**
 * MongoDB Database Connection Manager
 *
 * Environment Variables (set in Render Dashboard → Environment):
 * ┌─────────────────┬───────────────────────────────────────────────────────────┐
 * │ Variable        │ Example Value                                             │
 * ├─────────────────┼───────────────────────────────────────────────────────────┤
 * │ MONGODB_URI     │ mongodb+srv://user:pass@cluster.mongodb.net/?retryWrites=true │
 * │ MONGODB_DB_NAME │ coffee_shop                                               │
 * └─────────────────┴───────────────────────────────────────────────────────────┘
 *
 * Local development fallback: mongodb://localhost:27017
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use MongoDB\Client;
use MongoDB\Driver\Exception\Exception as MongoException;

class DatabaseConnection {
    private static ?MongoDB\Database $dbInstance = null;

    /**
     * Get a singleton connection to the MongoDB database.
     * Reads MONGODB_URI and MONGODB_DB_NAME from environment variables.
     *
     * @return MongoDB\Database
     */
    public static function getConnection(): MongoDB\Database {
        if (self::$dbInstance !== null) {
            return self::$dbInstance;
        }

        // ── Read from environment variables ──────────────────────────────
        // Render.com injects env vars at runtime via the Dashboard or render.yaml
        $uri    = getenv('MONGODB_URI')     ?: 'mongodb://localhost:27017';
        $dbName = getenv('MONGODB_DB_NAME') ?: 'coffee_shop';

        // Safety: never expose credentials in logs
        $safeUri = preg_replace('/\/\/([^:]+):([^@]+)@/', '//***:***@', $uri);

        try {
            // Build client options for Atlas (TLS is required for Atlas SRV URIs)
            $clientOptions = [];
            if (str_starts_with($uri, 'mongodb+srv://')) {
                // Atlas SRV — TLS is automatically negotiated, no extra options needed
                $clientOptions = [
                    'serverSelectionTimeoutMS' => 5000,  // fail fast if Atlas unreachable
                    'connectTimeoutMS'         => 5000,
                ];
            }

            $client = new Client($uri, $clientOptions);

            // Ping the database to verify connectivity on startup
            $client->selectDatabase($dbName)->command(['ping' => 1]);

            self::$dbInstance = $client->selectDatabase($dbName);
            error_log("MongoDB connected successfully to: {$safeUri} / db: {$dbName}");

            return self::$dbInstance;

        } catch (MongoException $e) {
            error_log("MongoDB Connection Error [{$safeUri}]: " . $e->getMessage());

            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Internal Server Error: Database connectivity issue.'
            ]);
            exit;
        }
    }

    /**
     * Reset singleton — useful for unit tests.
     */
    public static function reset(): void {
        self::$dbInstance = null;
    }
}
