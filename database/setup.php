<?php
// database/setup.php - Database installer and migration verification

require_once __DIR__ . '/../config/app.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>StemFlow AI Database Setup</title><style>body{background:#0D0D12;color:#22D3EE;font-family:monospace;padding:30px;line-height:1.6;}pre{background:#1B1B26;padding:20px;border-radius:8px;border:1px solid #272738;color:#fff;}a{color:#8B5CF6;}</style></head><body><h2>StemFlow AI — Database Setup</h2><pre>';
}

echo "=== StemFlow AI Database Setup ===\n";

$host = '127.0.0.1';
$port = 3306;
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
$user = 'root';
$pass = '';
$dbname = 'stemflow_db';

$dsnVariants = [
    "mysql:host=$host;port=$port;charset=utf8mb4",
    "mysql:unix_socket=$socket;charset=utf8mb4",
    "mysql:host=localhost;charset=utf8mb4"
];

$pdo = null;
foreach ($dsnVariants as $dsn) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "Connected to MySQL server using: $dsn\n";
        break;
    } catch (PDOException $e) {
        // Continue trying variants
    }
}

if (!$pdo) {
    die("Error: Could not connect to MySQL server. Please ensure XAMPP MySQL is running.\n");
}

try {
    $sqlFile = __DIR__ . '/stemflow_db.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file $sqlFile not found.\n");
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
    echo "Database '$dbname' and all relational tables verified and ready.\n";

    // Reconnect directly to stemflow_db
    $pdo->exec("USE `$dbname`");

    // Check if test user exists, otherwise create demo user
    $stmt = $pdo->query("SELECT id FROM users WHERE email = 'demo@stemflow.ai' LIMIT 1");
    if (!$stmt->fetch()) {
        $demoPass = password_hash('password123', PASSWORD_DEFAULT);
        $stmtInsert = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
        $stmtInsert->execute(['Demo Producer', 'demo@stemflow.ai', $demoPass]);
        echo "Created demo user: demo@stemflow.ai (password: password123)\n";
    }

    echo "Setup finished successfully.\n";
    if (!$isCli) {
        echo '</pre><p><a href="../index.php">&larr; Go to StemFlow AI Studio</a></p></body></html>';
    }
} catch (Exception $e) {
    echo "Setup failed: " . $e->getMessage() . "\n";
    if (!$isCli) {
        echo '</pre></body></html>';
    }
}
