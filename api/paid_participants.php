<?php
require __DIR__ . '/../includes/db.php';
$stmt = $pdo->query("SELECT o.order_code,u.name FROM orders o JOIN users u ON o.user_id=u.id WHERE o.status='paid'");
$data = $stmt->fetchAll();
header('Content-Type: application/json');
echo json_encode($data);
