<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
if (!file_exists($root . '/.env')) {
    $settings = [
        'APP_NAME' => 'Stockroom', 'APP_ENV' => 'development', 'APP_KEY' => bin2hex(random_bytes(32)),
        'DB_DRIVER' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306',
        'DB_USER' => 'root', 'DB_PASSWORD' => '', 'DB_NAME' => 'lab6_stockroom', 'DB_CHARSET' => 'utf8mb4', 'DB_PREFIX' => '',
        'DB_SSL_REQUIRED' => 'false', 'DB_SSL_CA' => '',
        'JWT_SECRET' => bin2hex(random_bytes(32)), 'REFRESH_TOKEN_KEY' => bin2hex(random_bytes(32)),
        'FRONTEND_ORIGIN' => 'http://localhost:5173,http://127.0.0.1:5173,http://localhost', 'MIGRATION_ENABLED' => 'false',
    ];
    file_put_contents($root . '/.env', implode(PHP_EOL, array_map(fn($k, $v) => "$k=$v", array_keys($settings), $settings)) . PHP_EOL);
    echo 'Created local .env with random secrets.' . PHP_EOL;
}
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value, " \t\r\n\"'"));
}
$host = getenv('DB_HOST');
if (!in_array($host, ['localhost', '127.0.0.1'], true)) exit('This script supports local MySQL only.' . PHP_EOL);
$name = getenv('DB_NAME');
if (!$name || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) exit('Invalid local database name.' . PHP_EOL);
$pdo = new PDO('mysql:host=' . $host . ';port=' . (getenv('DB_PORT') ?: 3306), getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Local database ready: $name" . PHP_EOL;
