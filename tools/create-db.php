<?php
// Creates the MySQL database if it does not exist (uses the DB_* environment variables set by install.bat)
$h = getenv('DB_HOST') ?: '127.0.0.1';
$p = getenv('DB_PORT') ?: '3306';
$u = getenv('DB_USER') ?: 'root';
$pw = getenv('DB_PASS') ?: '';
$n = getenv('DB_NAME') ?: 'larms';
if (! preg_match('/^[A-Za-z0-9_]+$/', $n)) { fwrite(STDERR, "Invalid database name\n"); exit(1); }
try {
    $pdo = new PDO("mysql:host=$h;port=$p", $u, $pw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$n` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "      Database '$n' is ready.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "      Cannot connect to MySQL: " . $e->getMessage() . "\n");
    exit(1);
}
