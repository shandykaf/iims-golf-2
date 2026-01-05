<?php
// api/categories.php
header('Content-Type: application/json');
require_once '../db.php'; // koneksi PDO $pdo

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $stmt = $pdo->query("SELECT * FROM dp_categories ORDER BY id DESC");
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
  $name = trim($input['name'] ?? '');
  $desc = $input['description'] ?? null;
  if (!$name) { http_response_code(400); echo json_encode(['error'=>'name required']); exit; }
  $stmt = $pdo->prepare("INSERT INTO dp_categories (name, description) VALUES (?, ?)");
  $stmt->execute([$name, $desc]);
  echo json_encode(['id' => $pdo->lastInsertId()]);
  exit;
}

if ($method === 'PUT') {
  $id = (int)($input['id'] ?? 0);
  $name = trim($input['name'] ?? '');
  $desc = $input['description'] ?? null;
  if (!$id || !$name) { http_response_code(400); echo json_encode(['error'=>'invalid']); exit; }
  $stmt = $pdo->prepare("UPDATE dp_categories SET name=?, description=? WHERE id=?");
  $stmt->execute([$name, $desc, $id]);
  echo json_encode(['ok'=>true]);
  exit;
}

if ($method === 'DELETE') {
  parse_str(file_get_contents("php://input"), $del);
  $id = (int)($del['id'] ?? 0);
  if (!$id) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
  $stmt = $pdo->prepare("DELETE FROM dp_categories WHERE id=?");
  $stmt->execute([$id]);
  echo json_encode(['ok'=>true]);
  exit;
}
?>
