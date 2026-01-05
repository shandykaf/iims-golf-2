<?php
// api/winners.php
header('Content-Type: application/json');
require_once '../db.php'; // $pdo

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $dp = isset($_GET['doorprize_id']) ? (int)$_GET['doorprize_id'] : null;
  if ($dp) {
    $stmt = $pdo->prepare("SELECT * FROM dp_winners WHERE doorprize_id=? ORDER BY created_at DESC");
    $stmt->execute([$dp]);
  } else {
    $stmt = $pdo->query("SELECT * FROM dp_winners ORDER BY created_at DESC");
  }
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// tandai pemenang baru
if ($method === 'POST') {
  $doorprize_id = (int)($input['doorprize_id'] ?? 0);
  $user_id = isset($input['user_id']) ? (int)$input['user_id'] : null;
  $winner_name = $input['winner_name'] ?? null;
  $winner_email = $input['winner_email'] ?? null;

  if (!$doorprize_id || (!$user_id && !$winner_name)) {
    http_response_code(400);
    echo json_encode(['error'=>'doorprize_id & winner required']);
    exit;
  }

  $stmt = $pdo->prepare("INSERT INTO dp_winners (doorprize_id, user_id, winner_name, winner_email) VALUES (?, ?, ?, ?)");
  $stmt->execute([$doorprize_id, $user_id, $winner_name, $winner_email]);

  echo json_encode(['id' => $pdo->lastInsertId()]);
  exit;
}

// update (mis. set prize_given true)
if ($method === 'PUT') {
  $id = (int)($input['id'] ?? 0);
  $prize_given = isset($input['prize_given']) ? (bool)$input['prize_given'] : null;
  if (!$id) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }

  if ($prize_given !== null) {
    if ($prize_given) {
      $stmt = $pdo->prepare("UPDATE dp_winners SET prize_given=1, given_at=NOW() WHERE id=?");
      $stmt->execute([$id]);
    } else {
      $stmt = $pdo->prepare("UPDATE dp_winners SET prize_given=0, given_at=NULL WHERE id=?");
      $stmt->execute([$id]);
    }
    echo json_encode(['ok'=>true]);
    exit;
  }

  echo json_encode(['ok'=>false]);
  exit;
}

// delete winner
if ($method === 'DELETE') {
  parse_str(file_get_contents("php://input"), $del);
  $id = (int)($del['id'] ?? 0);
  if (!$id) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
  $stmt = $pdo->prepare("DELETE FROM dp_winners WHERE id=?");
  $stmt->execute([$id]);
  echo json_encode(['ok'=>true]);
  exit;
}
?>
