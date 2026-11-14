<?php
// config/database.php - Database Configuration and PDO Connection

require_once __DIR__ . '/app.php';

class Database {
    private static ?PDO $instance = null;

    private static string $host = '127.0.0.1';
    private static int $port = 3306;
    private static string $dbname = 'stemflow_db';
    private static string $user = 'root';
    private static string $pass = '';
    private static string $socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    private static function connect(): void {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        // Connection attempts:
        // 1. Try TCP 127.0.0.1:3306
        // 2. Try localhost with XAMPP socket
        // 3. Try standard localhost
        $dsnVariants = [
            "mysql:host=" . self::$host . ";port=" . self::$port . ";dbname=" . self::$dbname . ";charset=utf8mb4",
            "mysql:unix_socket=" . self::$socket . ";dbname=" . self::$dbname . ";charset=utf8mb4",
            "mysql:host=localhost;dbname=" . self::$dbname . ";charset=utf8mb4"
        ];

        $lastException = null;

        foreach ($dsnVariants as $dsn) {
            try {
                self::$instance = new PDO($dsn, self::$user, self::$pass, $options);
                return;
            } catch (PDOException $e) {
                $lastException = $e;
                // If error is 1049 (Unknown database 'stemflow_db'), attempt auto-creation
                if ($e->getCode() == 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                    if (self::initializeDatabase($dsn)) {
                        self::$instance = new PDO($dsn, self::$user, self::$pass, $options);
                        return;
                    }
                }
            }
        }

        app_log("Database connection failed: " . ($lastException ? $lastException->getMessage() : "Unknown error"), 'ERROR');
        throw new Exception("Database connection failed. Please ensure MySQL is running in XAMPP: " . ($lastException ? $lastException->getMessage() : ""));
    }

    private static function initializeDatabase(string $originalDsn): bool {
        try {
            // Connect to server without dbname
            $baseDsn = preg_replace('/dbname=[^;]+;?/', '', $originalDsn);
            $pdo = new PDO($baseDsn, self::$user, self::$pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $sqlFile = ROOT_PATH . '/database/stemflow_db.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
                app_log("Database 'stemflow_db' created and initialized from schema file.", 'INFO');
                return true;
            }
        } catch (Exception $e) {
            app_log("Auto database initialization failed: " . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

function getDB(): PDO {
    return Database::getConnection();
}
