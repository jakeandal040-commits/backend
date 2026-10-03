<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
foreach (file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value, " \t\r\n\"'"));
}
if (getenv('APP_ENV') !== 'development' || !in_array(getenv('DB_HOST'), ['localhost', '127.0.0.1'], true)) exit('Demo seeding is limited to local development.' . PHP_EOL);
$db = new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4', getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$find = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
$find->execute(['admin', 'admin@stockroom.local']);
if (!$find->fetch()) {
    $db->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)')->execute(['admin', 'admin@stockroom.local', password_hash('Stockroom123!', PASSWORD_BCRYPT), 'admin']);
    echo 'Local demo account created: admin / Stockroom123!' . PHP_EOL;
} else echo 'Demo account already exists; password unchanged.' . PHP_EOL;
if ((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
    $insert = $db->prepare('INSERT INTO products (product_name, description, price, quantity) VALUES (?, ?, ?, ?)');
    foreach ([
        ['Canvas Tote Bag', 'An everyday carry, made with natural cotton canvas.', '349.00', 48],
        ['Ceramic Coffee Mug', 'A slow-morning essential. Matte finish, 350 ml.', '289.00', 32],
        ['Linen Notebook', 'Thoughts, plans, and possibilities. 120 dotted pages.', '199.00', 8],
        ['Desk Organizer', 'A place for the little things. Solid bamboo.', '599.00', 15],
        ['Stainless Water Bottle', 'Keep your day refreshed. Insulated, 500 ml.', '749.00', 0],
        ['Wireless Keyboard', 'A quieter workspace. Compact Bluetooth keyboard.', '1499.00', 24],
    ] as $product) $insert->execute($product);
    echo 'Created six sample products.' . PHP_EOL;
} else echo 'Existing product inventory unchanged.' . PHP_EOL;
