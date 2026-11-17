<?php
header('Content-Type: text/plain; charset=utf-8');
$cfg = require __DIR__ . '/db-config.php';
$pdo = new PDO(
    "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4",
    $cfg['user'],
    $cfg['password']
);
echo "--- messages DESCRIBE ---\n";
foreach ($pdo->query("DESCRIBE messages") as $c) {
    echo $c['Field'] . ' | ' . $c['Type'] . ' | ' . $c['Null'] . ' | ' . $c['Key'] . ' | ' . $c['Default'] . ' | ' . $c['Extra'] . "\n";
}
echo "\n--- buy DESCRIBE ---\n";
foreach ($pdo->query("DESCRIBE buy") as $c) {
    echo $c['Field'] . ' | ' . $c['Type'] . "\n";
}
echo "\n--- sample users ---\n";
foreach ($pdo->query("SELECT user_id, user_name FROM users ORDER BY user_id LIMIT 5") as $u) {
    echo $u['user_id'] . ' | ' . $u['user_name'] . "\n";
}
