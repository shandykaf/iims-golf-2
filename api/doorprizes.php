<?php
// api/doorprizes.php
header('Content-Type: application/json');
require_once '../db.php'; // $pdo

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  // optional ?category_id=...
  $cat = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
  if ($cat) {
    $stmt = $pdo->prepare("SELECT * FROM doorprizes WHERE category_id=? ORDER BY id DESC");
    $stmt->execute([$cat]);
  } else {
    $stmt = $pdo->query("SELECT * FROM doorprizes ORDER BY id DESC");
  }
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
  $category_id = (int)($input['category_id'] ?? 0);
  $title = trim($input['title'] ?? '');
  $qty = (int)($input['qty'] ?? 1);
  $notes = $input['notes'] ?? null;
  if (!$category_id || !$title) { http_response_code(400); echo json_encode(['error'=>'invalid']); exit; }
  $stmt = $pdo->prepare("INSERT INTO doorprizes (category_id, title, qty, notes) VALUES (?, ?, ?, ?)");
  $stmt->execute([$category_id, $title, $qty, $notes]);
  echo json_encode(['id' => $pdo->lastInsertId()]);
  exit;
}

if ($method === 'PUT') {
  $id = (int)($input['id'] ?? 0);
  $title = trim($input['title'] ?? '');
  $qty = (int)($input['qty'] ?? 1);
  $notes = $input['notes'] ?? null;
  if (!$id || !$title) { http_response_code(400); echo json_encode(['error'=>'invalid']); exit; }
  $stmt = $pdo->prepare("UPDATE doorprizes SET title=?, qty=?, notes=?, updated_at=NOW() WHERE id=?");
  $stmt->execute([$title, $qty, $notes, $id]);
  echo json_encode(['ok'=>true]);
  exit;
}

if ($method === 'DELETE') {
  parse_str(file_get_contents("php://input"), $del);
  $id = (int)($del['id'] ?? 0);
  if (!$id) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
  $stmt = $pdo->prepare("DELETE FROM doorprizes WHERE id=?");
  $stmt->execute([$id]);
  echo json_encode(['ok'=>true]);
  exit;
}
?>
