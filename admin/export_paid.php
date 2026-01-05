<?php
require __DIR__ . '/../includes/db.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=peserta_lunas.xls");

$stmt = $pdo->query("
  SELECT o.order_code, u.name, u.email, u.phone, u.handicap, u.institution, u.size
  FROM orders o
  JOIN users u ON u.id = o.user_id
  WHERE o.status = 'paid'
");

echo "Order Code\tNama\tEmail\tHP\tHandicap\tInstansi\tSize\n";
while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  echo implode("\t", $r) . "\n";
}
