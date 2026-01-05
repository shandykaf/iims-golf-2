<?php
// admin/review.php - FIXED & STANDARDIZED
session_start();

// 1. CEK LOGIN
if (empty($_SESSION['admin_logged_in'])) {
    $cur = $_SERVER['REQUEST_URI'] ?? '/admin/review.php';
    header('Location: login.php?next=' . urlencode($cur));
    exit;
}

// 2. LOAD INCLUDES (OPSI PATH LUAR ADMIN)
require __DIR__ . '/../includes/db.php';
$config = require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/email.php';

// Helper URL File
function buildReviewFileUrl($fname, $config) {
    // Pastikan akses array config benar
    $baseUrl = rtrim($config['app']['base_url'], '/');
    return $baseUrl . '/uploads/' . $fname;
}

// Helper Kirim Email Status (Internal Function agar aman)
function sendPaymentStatusEmail($toEmail, $toName, $status, $orderCode, $fileUrl = '') {
    global $config;
    try {
        // Cek apakah fungsi initMailer ada (dari email.php)
        if (!function_exists('initMailer')) {
            return false; 
        }
        $mail = initMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);

        if ($status === 'paid') {
            $mail->Subject = 'Pembayaran Dikonfirmasi - ' . $orderCode;
            $mail->Body = '
            <div style="background:#f4f6f8;padding:30px 0;font-family:Arial,Helvetica,sans-serif;">
                <div style="max-width:600px;margin:0 auto;background:#ffffff;padding:30px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.05);">
        
                    <p>Yth. Bapak/Ibu <strong>' . htmlspecialchars($user['name']) . '</strong>,</p>
        
                    <p>
                        Kami informasikan bahwa pembayaran untuk <strong>Order ' . htmlspecialchars($orderCode) . '</strong>
                        telah kami terima dan berhasil diverifikasi.
                    </p>
        
                    <p>
                        Dengan demikian, status pendaftaran Anda saat ini adalah 
                        <strong>LUNAS (PAID)</strong>.
                    </p>
        
                    <p>
                        Sebagai informasi, kode order tersebut merupakan bukti resmi keikutsertaan dan kehadiran
                        Bapak/Ibu pada kegiatan <strong>IIMS Golf Tournament</strong>. Mohon untuk menyimpan kode ini
                        dan menunjukkannya pada saat proses registrasi di lokasi acara.
                    </p>
        
                    <p>
                        Terima kasih atas partisipasi dan kepercayaan Bapak/Ibu. Kami menantikan kehadiran Anda
                        dalam kegiatan IIMS Golf Tournament.
                    </p>
        
                    <hr style="border:none;border-top:1px solid #e0e0e0;margin:25px 0;">
        
                    <p style="font-size:13px;color:#555;">
                        Apabila terdapat pertanyaan lebih lanjut, silakan hubungi kami melalui:<br>
                        Email: <a href="mailto:contact@iimsgolftournament.com">contact@iimsgolftournament.com</a><br>
                        WhatsApp: <a href="https://wa.me/6287890764125" target="_blank">0878 9076 4125</a><br>
                        Instagram: <a href="https://instagram.com/iimsgolftournament" target="_blank">@iimsgolftournament</a>
                    </p>
        
                    <p style="margin-top:25px;">
                        Hormat kami,<br>
                        <strong>Panitia IIMS Golf Tournament 2026</strong><br>
                        Dyandra Promosindo
                    </p>
        
                </div>
            </div>
        ';
        } else {
            $mail->Subject = 'Masalah pada Bukti Pembayaran - ' . $orderCode;
            $mail->Body = '
                <div style="background:#f4f6f8;padding:30px 0;font-family:Arial,Helvetica,sans-serif;">
                    <div style="max-width:600px;margin:0 auto;background:#ffffff;padding:30px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.05);">
            
                        <p>Yth. Bapak/Ibu,</p>
            
                        <p>
                            Kami informasikan bahwa bukti pembayaran untuk <strong>Order ' . htmlspecialchars($orderCode) . '</strong>
                            belum dapat kami verifikasi.
                        </p>
            
                        <p>
                            Hal ini dapat disebabkan oleh beberapa kemungkinan, seperti bukti pembayaran yang kurang jelas,
                            data yang tidak sesuai, atau informasi yang belum lengkap. Oleh karena itu, kami mohon kesediaan
                            Bapak/Ibu untuk melakukan pengecekan kembali serta mengunggah ulang bukti pembayaran yang valid.
                        </p>
            
                        <p>
                            Apabila terdapat kendala atau hal yang perlu dikonfirmasi, kami dengan senang hati siap membantu.
                        </p>
            
                        <p style="color:#555;">
                            Email: <a href="mailto:contact@iimsgolftournament.com">contact@iimsgolftournament.com</a><br>
                            WhatsApp: <a href="https://wa.me/6287890764125" target="_blank">0878 9076 4125</a><br>
                            Instagram: <a href="https://instagram.com/iimsgolftournament" target="_blank">@iimsgolftournament</a>
                        </p>
            
                        <p style="margin-top:25px;">
                            Hormat kami,<br>
                            <strong>Panitia IIMS Golf Tournament 2026</strong><br>
                            Dyandra Promosindo
                        </p>
            
                    </div>
                </div>
            ';
        }
        return $mail->send();
    } catch (Exception $e) {
        // Silent error agar tidak crash 500
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}

// 3. GET DATA
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
if ($payment_id <= 0) {
    header('Location: dashboard.php'); 
    exit;
}

// 4. HANDLE POST ACTION (APPROVE / REJECT)
$flash = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['approve','reject'])) {
        try {
            $pdo->beginTransaction();

            // Ambil data detail untuk proses
            $stmt = $pdo->prepare("
                SELECT p.*, o.id AS order_id, o.order_code, u.email AS user_email, u.name AS user_name
                FROM payment_uploads p
                JOIN orders o ON p.order_id = o.id
                JOIN users u ON o.user_id = u.id
                WHERE p.id = ?
                LIMIT 1
            ");
            $stmt->execute([$payment_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) throw new Exception("Data pembayaran tidak ditemukan.");

            $orderId   = (int)$row['order_id'];
            $orderCode = $row['order_code'];
            $userEmail = $row['user_email'];
            $userName  = $row['user_name'];

            if ($action === 'approve') {
                // Update Order jadi PAID
                $pdo->prepare("UPDATE orders SET status='paid' WHERE id=?")->execute([$orderId]);
                
                // Kirim Email
                sendPaymentStatusEmail($userEmail, $userName, 'paid', $orderCode);

                $flash = "Pembayaran berhasil di-APPROVE. Status order kini PAID.";
            } else {
                // Update Order jadi REJECTED
                $pdo->prepare("UPDATE orders SET status='rejected' WHERE id=?")->execute([$orderId]);
                
                // Kirim Email
                sendPaymentStatusEmail($userEmail, $userName, 'rejected', $orderCode);

                $flash = "Pembayaran telah di-REJECT.";
                $msgType = 'warning';
            }

            $pdo->commit();

            // Redirect agar tidak resubmit form
            header("Location: review.php?payment_id={$payment_id}&updated=1&msg=" . urlencode($flash) . "&type=" . $msgType);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $flash = "Terjadi kesalahan: " . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// 5. FETCH DATA UTAMA UNTUK TAMPILAN
// FIX: Menambahkan field phone, handicap, institution, size
$stmt = $pdo->prepare("
    SELECT p.*, 
           o.order_code, o.status AS order_status, 
           u.name AS user_name, u.email AS user_email,
           u.phone, u.handicap, u.institution, u.size
    FROM payment_uploads p
    JOIN orders o ON p.order_id = o.id
    JOIN users u ON o.user_id = u.id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) die("Data pembayaran tidak ditemukan.");

// Siapkan File URL
$fileUrl = buildReviewFileUrl($payment['file_path'], $config);
$ext = strtolower(pathinfo($payment['file_path'], PATHINFO_EXTENSION));
$isImage = in_array($ext, ['jpg','jpeg','png']);

// Status Flash Message dari URL
if (isset($_GET['updated']) && isset($_GET['msg'])) {
    $flash = $_GET['msg'];
    $msgType = $_GET['type'] ?? 'success';
}

function statusBadgeReview($s) {
    $cls = 'secondary';
    if ($s === 'pending') $cls = 'warning';
    elseif ($s === 'uploaded') $cls = 'info';
    elseif ($s === 'paid') $cls = 'success';
    elseif ($s === 'rejected') $cls = 'danger';
    return "<span class='badge bg-{$cls}'>" . htmlspecialchars(strtoupper($s)) . "</span>";
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Review Pembayaran</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background:#f9fafc; }
    .container-box { max-width:1100px; margin:30px auto; }
    .table-detail th { width: 180px; background-color: #f8f9fa; }
    .img-preview { max-width: 100%; border: 1px solid #dee2e6; border-radius: 8px; padding: 4px; background: #fff; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom mb-4 shadow-sm">
  <div class="container-fluid">
    <span class="navbar-brand fw-bold text-primary">Admin Panel</span>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="doorprizes.php">Doorprize</a></li>
      </ul>
      <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container-box">
    
    <a href="dashboard.php" class="btn btn-outline-secondary mb-3">&larr; Kembali ke Dashboard</a>

    <?php if ($flash): ?>
        <div class="alert alert-<?=htmlspecialchars($msgType)?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Detail Peserta & Order</div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0 table-detail">
                        <tr>
                            <th>Order Code</th>
                            <td class="fw-bold text-primary"><?=htmlspecialchars($payment['order_code'])?></td>
                        </tr>
                        <tr>
                            <th>Status Saat Ini</th>
                            <td><?=statusBadgeReview($payment['order_status'])?></td>
                        </tr>
                        <tr>
                            <th>Tanggal Upload</th>
                            <td><?=htmlspecialchars($payment['uploaded_at'])?></td>
                        </tr>
                        <tr><td colspan="2" class="bg-light fw-bold text-center">Data Peserta</td></tr>
                        <tr>
                            <th>Nama Lengkap</th>
                            <td><?=htmlspecialchars($payment['user_name'])?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?=htmlspecialchars($payment['user_email'])?></td>
                        </tr>
                        <tr>
                            <th>No HP / WA</th>
                            <td><?=htmlspecialchars($payment['phone'])?></td>
                        </tr>
                        <tr>
                            <th>Handicap</th>
                            <td><?=htmlspecialchars($payment['handicap'])?></td>
                        </tr>
                        <tr>
                            <th>Instansi</th>
                            <td><?=htmlspecialchars($payment['institution'])?></td>
                        </tr>
                        <tr>
                            <th>Ukuran Baju</th>
                            <td><?=htmlspecialchars($payment['size'])?></td>
                        </tr>
                    </table>
                </div>
                <div class="card-footer bg-white">
                    <form method="post" onsubmit="return confirm('Apakah Anda yakin ingin memproses status ini? Email notifikasi akan dikirim ke user.');">
                        <div class="d-flex gap-2">
                            <button name="action" value="approve" class="btn btn-success flex-grow-1" 
                                <?= ($payment['order_status'] === 'paid') ? 'disabled' : '' ?>>
                                <i class="bi bi-check-circle"></i> Approve (Set PAID)
                            </button>
                            
                            <button name="action" value="reject" class="btn btn-danger flex-grow-1"
                                <?= ($payment['order_status'] === 'paid') ? 'disabled' : '' ?>>
                                <i class="bi bi-x-circle"></i> Reject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">Bukti Pembayaran</div>
                <div class="card-body text-center bg-light">
                    <?php if ($isImage): ?>
                        <a href="<?=$fileUrl?>" target="_blank">
                            <img src="<?=$fileUrl?>" class="img-preview" alt="Bukti Bayar">
                        </a>
                        <div class="mt-3 text-muted small">Klik gambar untuk memperbesar</div>
                    <?php else: ?>
                        <div class="py-5">
                            <p class="mb-3">File bukan gambar (PDF/Lainnya)</p>
                            <a href="<?=$fileUrl?>" class="btn btn-primary" target="_blank">Download / Buka File</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>