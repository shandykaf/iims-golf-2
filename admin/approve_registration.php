<?php
// admin/approve_registration.php - FIXED VERSION
session_start();

// 1. Cek Login (Pastikan Session Key SAMA dengan Dashboard & Login)
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// 2. Load DB
// Sesuaikan path includes (pilih salah satu opsi di bawah sesuai struktur folder)
require __DIR__ . '/../includes/db.php';     // Opsi jika includes di LUAR admin
// require __DIR__ . '/includes/db.php';     // Opsi jika includes di DALAM admin

require __DIR__ . '/../includes/email.php';  // Load helper email

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$user_id  = (int)($_POST['user_id']  ?? 0);
$order_id = (int)($_POST['order_id'] ?? 0);

if ($user_id <= 0 || $order_id <= 0) {
    header('Location: dashboard.php?error=invalid_id');
    exit;
}

try {
    // Ambil data user & order
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, o.order_code 
        FROM users u
        JOIN orders o ON o.user_id = u.id
        WHERE u.id = ? AND o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $order_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // UPDATE STATUS MENJADI 'approved'
        // Pastikan Anda sudah menjalankan SQL ALTER TABLE agar 'approved' diterima DB
        $upd = $pdo->prepare("UPDATE orders SET status = 'approved' WHERE id = ?");
        $upd->execute([$order_id]);

        // Kirim Email Instruksi
        // Pastikan parameter yang dikirim sesuai dengan fungsi di email.php
        sendUserPaymentInstruction(
            ['name' => $row['name'], 'email' => $row['email']],
            ['order_code' => $row['order_code']]
        );
    }

} catch (Exception $e) {
    // Silent error, log only
    error_log($e->getMessage());
}

// Redirect kembali ke Dashboard tab Approved
header('Location: dashboard.php?msg=approved');
exit;