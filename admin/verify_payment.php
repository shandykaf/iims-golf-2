<?php
// admin/verify_payment.php
// Verifies an order (mark as paid), sends email to user, returns JSON result.

// SECURITY: require admin session/login. Sesuaikan dengan implementasimu.
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Input (POST)
$order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order_id']);
    exit;
}

// Load config
$config = require __DIR__ . '/../includes/config.php';

// DB connect (mysqli)
$mysqli = new mysqli($config->db->host, $config->db->user, $config->db->pass, $config->db->name);
if ($mysqli->connect_errno) {
    error_log("DB connect error: " . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Begin transaction
$mysqli->begin_transaction();

try {
    // 1) Fetch order + user data (for email)
    $sql = "SELECT o.id AS order_id, o.order_code, o.amount, o.status, u.id AS user_id, u.name, u.email
            FROM orders o
            JOIN users u ON u.id = o.user_id
            WHERE o.id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $mysqli->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // If already paid, return ok (idempotent)
    if ($order['status'] === 'paid') {
        $mysqli->commit();
        echo json_encode(['success' => true, 'message' => 'Order already paid', 'order' => $order]);
        exit;
    }

    // 2) Update order status -> paid
    $upd = $mysqli->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
    $upd->bind_param('i', $order_id);
    $ok = $upd->execute();
    $upd->close();

    if (!$ok) throw new Exception("Failed to update order status");

    // 3) (Optional) insert into winners or audit table — contoh insert ke winners
    $ins = $mysqli->prepare("INSERT INTO winners (order_id, created_at) VALUES (?, NOW())");
    $ins->bind_param('i', $order_id);
    $ins->execute();
    $ins->close();

    // Commit transaction before sending email (so DB change is persisted)
    $mysqli->commit();

    // 4) Send email to user (uses includes/sendEmail already available)
    require_once __DIR__ . '/../includes/email.php';

    $to = $order['email'];
    $subject = "Pembayaran Anda Berhasil — {$order['order_code']}";
    $html = buildPaymentSuccessEmail($order['name'] ?? 'Peserta', $order['order_code'], $order['amount'], $config);

    $sent = false;
    try {
        $sent = sendEmail($to, $subject, $html);
    } catch (Throwable $e) {
        error_log("sendEmail throwable: " . $e->getMessage());
        $sent = false;
    }

    // 5) Return JSON result
    echo json_encode([
        'success' => true,
        'message' => 'Order marked as paid',
        'email_sent' => $sent,
        'order_id' => (int)$order_id
    ]);
    exit;

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("verify_payment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}

// helper: build email HTML
function buildPaymentSuccessEmail($name, $order_code, $amount, $config) {
    $site = rtrim($config->site_url, '/');
    $formatted = number_format((float)$amount, 0, ',', '.'); // Rp format, no currency sign
    $html = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Pembayaran Berhasil</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#222">
  <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto">
    <tr>
      <td style="padding:20px 0;text-align:center">
        <h2 style="margin:0">IIMS — Konfirmasi Pembayaran</h2>
      </td>
    </tr>
    <tr>
      <td style="background:#fff;padding:20px;border-radius:6px">
        <p>Halo <strong>{$name}</strong>,</p>
        <p>Terima kasih. Pembayaran Anda untuk pesanan <strong>{$order_code}</strong> telah kami verifikasi dan dinyatakan <strong>BERHASIL</strong>.</p>
        <p><strong>Detail:</strong><br>
           Order: {$order_code}<br>
           Jumlah: Rp {$formatted}</p>
        <p>Jika ada pertanyaan, balas email ini atau hubungi admin di: <a href="mailto:{$config->admin_email}">{$config->admin_email}</a>.</p>
        <p>Salam,<br><strong>IIMS Team</strong></p>
        <hr>
        <p style="font-size:12px;color:#888">Pesan ini dikirim otomatis oleh sistem. Jangan balas ke alamat ini jika bukan alamat admin.</p>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    return $html;
}