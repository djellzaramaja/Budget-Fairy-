<?php
declare(strict_types=1);

define('BF_ROOT', dirname(__DIR__));
$GLOBALS['bf_config'] = require BF_ROOT . '/config/config.php';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/FinanceEngine.php';
require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/AiClient.php';

if (config('env') === 'production') {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = config('db');
    try {
        $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,   // rowCount() = rows matched, so "no change" updates still count
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        if (PHP_SAPI === 'cli') exit("Database connection failed.\n");
        http_response_code(500);
        exit('Database connection failed. Check config/config.php and that MySQL is running.');
    }
    return $pdo;
}

if (PHP_SAPI !== 'cli') start_secure_session();
