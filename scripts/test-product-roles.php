<?php
// Owns temporary accounts and cleans them up after the HTTP integration test.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
foreach (file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value, " \t\r\n\"'"));
}
if (getenv('APP_ENV') !== 'development' || !in_array(getenv('DB_HOST'), ['localhost', '127.0.0.1'], true)) exit('Role tests require a local development database.' . PHP_EOL);
$db = new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4', getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$suffix = bin2hex(random_bytes(6));
$usernames = ['role_user_' . $suffix, 'role_admin_' . $suffix, 'role_register_' . $suffix];
$password = bin2hex(random_bytes(18));
putenv('TEST_ROLE_USER=' . $usernames[0]);
putenv('TEST_ROLE_ADMIN=' . $usernames[1]);
putenv('TEST_ROLE_REGISTER=' . $usernames[2]);
putenv('TEST_ROLE_PASSWORD=' . $password);
$code = 1;
try {
    $insert = $db->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
    $insert->execute([$usernames[0], $usernames[0] . '@test.local', password_hash($password, PASSWORD_BCRYPT), 'user']);
    $insert->execute([$usernames[1], $usernames[1] . '@test.local', password_hash($password, PASSWORD_BCRYPT), 'admin']);
    passthru('node ' . escapeshellarg(__DIR__ . '/test-product-roles.mjs'), $code);
} finally {
    $find = $db->prepare('SELECT id FROM users WHERE username IN (?, ?, ?)');
    $find->execute($usernames);
    foreach ($find->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $db->prepare('DELETE FROM refresh_tokens WHERE user_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
exit($code);
