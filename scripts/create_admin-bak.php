<?php
// scripts/create_admin.php
require __DIR__ . '/../includes/db.php'; // pastikan path benar

$username = 'gevio';         // ganti sesuai keinginan
$password = 'gevio-admin-panel';     // ganti sesuai keinginan
$email = 'gevio.dyandra@gmail.com';// opsional

$password_hash = password_hash($password, PASSWORD_DEFAULT);
// buat token panjang
$api_token = bin2hex(random_bytes(32));

$stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, api_token, email) VALUES (?, ?, ?, ?)");
$stmt->execute([$username, $password_hash, $api_token, $email]);

echo "Admin created:\nusername: {$username}\npassword: {$password}\napi_token: {$api_token}\n";
