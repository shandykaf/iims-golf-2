<?php
// payment.php (VERSI ROOT - FIX UPLOAD PERMISSION)
require __DIR__ . '/includes/db.php';
$config = require __DIR__ . '/includes/config.php';

$order_code = $_GET['order'] ?? '';
$order = null;
$has_uploaded = false;
$upload_success = false;
$error_msg = '';

if ($order_code) {
  // 1. Ambil Data Order
  $stmt = $pdo->prepare("SELECT o.*, u.name, u.email FROM orders o JOIN users u ON o.user_id=u.id WHERE o.order_code = ?");
  $stmt->execute([$order_code]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$order) { die('Order not found'); }

  // 2. Cek Apakah Sudah Pernah Upload?
  $stmtCheck = $pdo->prepare("SELECT id FROM payment_uploads WHERE order_id = ?");
  $stmtCheck->execute([$order['id']]);
  if ($stmtCheck->rowCount() > 0) {
      $has_uploaded = true;
  }

  // 3. Proses Upload
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$has_uploaded) {
      if (isset($_FILES['payment_file']) && $_FILES['payment_file']['error'] === 0) {
          $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
          $filename = $_FILES['payment_file']['name'];
          $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

          if (in_array($ext, $allowed)) {
              // Generate nama file
              $new_name = 'pay_' . time() . '_' . $order['id'] . '.' . $ext;
              
              // --- PERBAIKAN: Definisikan Folder Tujuan ---
              $target_dir = __DIR__ . '/uploads/';
              
              // Cek apakah folder ada? Jika tidak, buat otomatis!
              if (!is_dir($target_dir)) {
                  mkdir($target_dir, 0755, true);
              }

              $dest = $target_dir . $new_name;

              if (move_uploaded_file($_FILES['payment_file']['tmp_name'], $dest)) {
                  // Simpan ke DB
                  $stmtInsert = $pdo->prepare("INSERT INTO payment_uploads (order_id, user_id, file_path) VALUES (?, ?, ?)");
                  $stmtInsert->execute([$order['id'], $order['user_id'], $new_name]);

                  // Update status order
                  $stmtUpdate = $pdo->prepare("UPDATE orders SET status = 'uploaded' WHERE id = ?");
                  $stmtUpdate->execute([$order['id']]);

                  $has_uploaded = true; 
                  $upload_success = true; 
              } else {
                  // Debugging: Tampilkan error spesifik folder jika gagal
                  $error_msg = "Gagal simpan ke folder: " . $target_dir . ". Cek Permission Folder.";
              }
          } else {
              $error_msg = "Format file salah. Hanya JPG, PNG, PDF.";
          }
      } else {
          $error_msg = "Pilih file terlebih dahulu / File terlalu besar.";
      }
  }

} else {
    die('Invalid Request');
}

// ... (SISA KODE LOGIKA TIMER & ASSETS SAMA SEPERTI SEBELUMNYA)
// LOGIKA TIMER: Created At + 2 Hari
$createdTime = strtotime($order['created_at']);
$deadlineTime = $createdTime + (2 * 24 * 3600); 

// LOGIKA PATH ASSETS
$baseUrl = rtrim($config['app']['base_url'], '/');
$cssUrl  = '/assets/css/register.css'; 
$logoUrl = is_object($config) ? ($config->logo ?? 'assets/img/img-logo-black.png') : ($config['logo'] ?? 'assets/img/img-logo-black.png');

if (strpos($logoUrl, 'http') === false) {
    $logoUrl = $baseUrl . '/' . ltrim($logoUrl, '/');
}
?>
<!doctype html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta charset="utf-8">
  <title>Konfirmasi Pembayaran</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Spline+Sans:wght@300;400;600&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="<?= htmlspecialchars($cssUrl) ?>">
  
  <style>
    .sr-only{ position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }
    
    /* STYLE TAMBAHAN KHUSUS TIMER */
    .timer-container {
        text-align: center;
        margin-bottom: 25px;
        padding: 15px;
        background-color: #fff3cd;
        border: 1px solid #ffeeba;
        border-radius: 10px;
        color: #856404;
    }
    .timer-countdown {
        display: block;
        font-size: 20px;
        font-weight: 700;
        color: #d82323; 
        margin-top: 5px;
    }
    /* Style untuk pesan sukses (mengikuti desain confirm-card) */
    .success-message {
        text-align: center;
        padding: 40px 20px;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    }
    .success-icon {
        font-size: 50px;
        color: #28a745;
        margin-bottom: 15px;
    }
  </style>
</head>
<body class="bg-light">

<header class="register-header">
  <img src="<?= htmlspecialchars($logoUrl) ?>" class="reg-logo" alt="Logo">
</header>

<div class="register-section">
    <h2 class="register-title">KONFIRMASI PEMBAYARAN</h2>
    
    <?php if($error_msg): ?>
        <div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:10px; margin-bottom:20px; text-align:center;">
            <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <?php if (!$has_uploaded): ?>
        <div class="timer-container">
            <span>Batas Waktu Pembayaran:</span>
            <span id="countdown" class="timer-countdown">Loading...</span>
        </div>

        <div class="info-card">
          <p class="info-title">Informasi Pembayaran</p>
          <div class="info-data">
            <span class="label">Bank</span>
            <span class="data-number">Danamon</span>
          </div>
          <hr>
          <div class="info-data">
            <span class="label">No. Rekening</span>
            <span class="data-number">0077 1007 7772 <button class="btn-copy" id="copyAcct" type="button">COPY</button></span>
            <span class="note-number">PT DYANDRA PROMOSINDO</span>
          </div>
          <hr>
          <div class="info-data">
            <span class="label">Jumlah</span>
            <span class="data-number">Rp 1.800.000 <button class="btn-copy" id="copyAmt" type="button">COPY</button></span>
          </div>
        </div>

        <form id="uploadForm" class="confirm-card" action="" method="post" enctype="multipart/form-data">
          <div class="confirm-info">
            <span>Drag & drop media files to Upload</span>
            <span>or</span>
            <input class="file-input" type="file" name="payment_file" accept=".jpg,.jpeg,.png,.pdf" required>
          </div>
          <button class="btn-submit">UPLOAD FILE & KONFIRMASI PEMBAYARAN</button>
        </form>

    <?php else: ?>
        <div class="confirm-card success-message">
            <div class="success-icon">✓</div>
            <h3 style="color:#333; margin-bottom:10px;">Bukti Pembayaran Diterima</h3>
            <p style="color:#666; line-height:1.5;">
                Halo <strong><?= htmlspecialchars($order['name']) ?></strong>,<br>
                Terima kasih sudah mengupload bukti transfer.<br>
                Admin kami akan segera memverifikasi data Anda.
            </p>
            <div style="margin-top:20px; font-size:12px; color:#999;">
                Status Order: <span style="color:#28a745; font-weight:bold;">Menunggu Verifikasi</span>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
// Hanya jalankan script tombol copy jika elemennya ada (saat belum upload)
if(document.getElementById('copyAcct')) {
    document.getElementById('copyAcct').addEventListener('click', function(){
      navigator.clipboard.writeText('007710077772');
      alert('Rekening disalin');
    });
    document.getElementById('copyAmt').addEventListener('click', function(){
      navigator.clipboard.writeText('1800000');
      alert('Jumlah disalin');
    });
}

// TIMER SCRIPT (Tetap jalan di background)
(function() {
    var deadlineStr = "<?= date('Y-m-d\TH:i:s', $deadlineTime) ?>";
    var deadline = new Date(deadlineStr).getTime();

    var x = setInterval(function() {
        var now = new Date().getTime();
        var distance = deadline - now;
        
        var el = document.getElementById("countdown");
        if (!el) { return; } // Stop jika elemen tidak ada (sudah upload)

        if (distance < 0) {
            clearInterval(x);
            el.innerHTML = "WAKTU HABIS";
            el.style.color = "grey";
            return;
        }
        
        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
        var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        el.innerHTML = days + "h " + hours + "j " + minutes + "m " + seconds + "d ";
    }, 1000);
})();
</script>
</body>
</html>