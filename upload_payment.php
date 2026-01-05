<?php
// upload_payment.php (VERSI ROOT - FIX PATH)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// PERUBAHAN DI SINI:
// Hapus "/admin"
require __DIR__ . '/includes/db.php';
$config = require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/email.php'; // <-- Cek juga file ini ada di folder includes kan?

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die('Method not allowed'); }

$order_code = trim($_POST['order_code'] ?? '');
if ($order_code === '') { die('Order code required'); }

// Cek Order
$stmt = $pdo->prepare("SELECT o.*, u.email, u.name FROM orders o JOIN users u ON o.user_id = u.id WHERE order_code = ?");
$stmt->execute([$order_code]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) { die('Order not found'); }

// Cek File
if (!isset($_FILES['payment_file']) || $_FILES['payment_file']['error'] !== UPLOAD_ERR_OK) {
    die('File upload error. Silakan pilih file.');
}

$file = $_FILES['payment_file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','pdf'];

if (!in_array($ext, $allowed)) { die('Format file tidak diizinkan.'); }

// FIX: Upload ke folder uploads (di root)
// Pastikan folder ini ada dan permissionnya 755 atau 777
$uploadDir = __DIR__ . '/uploads'; 
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

$filename = 'pay_' . time() . '_' . $order['id'] . '.' . $ext;
$destPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) { die('Gagal menyimpan file.'); }

// Simpan DB
try {
    $pdo->beginTransaction();
    
    // Insert payment
    $stmt = $pdo->prepare("INSERT INTO payment_uploads (order_id, user_id, file_path, uploaded_at) VALUES (:oid, :uid, :fpath, NOW())");
    $stmt->execute([':oid' => $order['id'], ':uid' => $order['user_id'], ':fpath' => $filename]);
    
    $paymentId = $pdo->lastInsertId();
    
    // Update status order
    $upd = $pdo->prepare("UPDATE orders SET status = 'uploaded' WHERE id = ?");
    $upd->execute([$order['id']]);
    
    $pdo->commit();

    // Kirim Email Notif
    // URL publik untuk file
    $publicFileUrl = rtrim($config['app']['base_url'], '/') . '/uploads/' . $filename;
    
    sendAdminPaymentNotification([
        'name'       => $order['name'],
        'order_code' => $order['order_code']
    ], $publicFileUrl);

    // Redirect ke thankyou.php di root
    header("Location: thankyou.php?order=" . urlencode($order_code));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die('Error DB: ' . $e->getMessage());
}