<?php
// admin/dashboard.php - FINAL UPDATE: CUSTOM WA TEXT
session_start();

/* ===============================
 * AUTH
 * =============================== */
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

/* ===============================
 * INCLUDES
 * =============================== */
require __DIR__ . '/../includes/db.php';
$config = require __DIR__ . '/../includes/config.php';

/* ===============================
 * HELPERS
 * =============================== */
function buildPublicFileUrl($fname, $config) {
    return rtrim($config['app']['base_url'], '/') . '/uploads/' . $fname;
}

/* =====================================================
 * QUERY 1: REGISTRASI BARU (Pending)
 * ===================================================== */
$stmtNew = $pdo->query("
    SELECT u.id user_id, u.name, u.email, u.phone, u.handicap, u.institution, u.size,
           o.id order_id, o.order_code, o.created_at
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN payment_uploads p ON p.order_id = o.id
    WHERE o.status = 'pending'
      AND p.id IS NULL
    ORDER BY o.created_at DESC
");
$newRegs = $stmtNew->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
 * QUERY 2: SUDAH DI-APPROVE (Approved)
 * ===================================================== */
$stmtApproved = $pdo->query("
    SELECT u.id user_id, u.name, u.email, u.phone, u.handicap, u.institution, u.size,
           o.order_code, o.created_at, o.status
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE (o.status = 'approved' OR o.status = '') 
      AND o.id NOT IN (SELECT order_id FROM payment_uploads)
    ORDER BY o.created_at DESC
");
$approvedRegs = $stmtApproved->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
 * QUERY 3: PEMBAYARAN (Uploaded)
 * ===================================================== */
$stmtPayment = $pdo->query("
    SELECT u.id user_id, u.name, u.email, u.phone, u.handicap, u.institution, u.size,
           o.order_code, o.status,
           p.id payment_id, p.file_path, p.uploaded_at
    FROM payment_uploads p
    JOIN orders o ON p.order_id = o.id
    JOIN users u ON o.user_id = u.id
    WHERE o.status = 'uploaded'
    ORDER BY p.uploaded_at DESC
");
$paymentRows = $stmtPayment->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
 * QUERY 4: LUNAS (Paid)
 * ===================================================== */
$stmtPaid = $pdo->query("
    SELECT u.id user_id, u.name, u.email, u.phone, u.handicap, u.institution, u.size,
           o.order_code, o.created_at
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.status = 'paid'
    ORDER BY o.created_at DESC
");
$paidUsers = $stmtPaid->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
 * QUERY 5: DITOLAK / REJECTED (New)
 * ===================================================== */
$stmtRejected = $pdo->query("
    SELECT u.id user_id, u.name, u.email, u.phone, u.handicap, u.institution, u.size,
           o.order_code, o.created_at, o.status
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.status = 'rejected'
    ORDER BY o.created_at DESC
");
$rejectedUsers = $stmtRejected->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Admin Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    body { background:#f9fafc; font-size: 14px; }
    .container-box { max-width:1200px; margin:30px auto; }
    .table thead th { font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; background-color: #f1f3f5; border-bottom: 2px solid #dee2e6; }
    .btn-xs { padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 0.2rem; }
    .nav-tabs .nav-link.active { font-weight: bold; border-bottom: 3px solid #0d6efd; }
    
    /* Styling Modal Image */
    #paymentProofImg { max-width: 100%; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 5px; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom mb-4 shadow-sm">
  <div class="container-fluid">
    <span class="navbar-brand fw-bold text-primary">Admin Panel</span>
    <ul class="navbar-nav me-auto">
      <li class="nav-item"><span class="nav-link active">Dashboard</span></li>
      <li class="nav-item"><a class="nav-link" href="doorprizes.php">Doorprize</a></li>
    </ul>
    <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
  </div>
</nav>

<div class="container-box">

<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-new">
      Registrasi Baru
      <?php if(count($newRegs)): ?><span class="badge bg-danger ms-1"><?=count($newRegs)?></span><?php endif; ?>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-approved">
      Sudah Di-approve
      <?php if(count($approvedRegs)): ?><span class="badge bg-primary ms-1"><?=count($approvedRegs)?></span><?php endif; ?>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payment">
        Pembayaran
        <?php if(count($paymentRows)): ?><span class="badge bg-warning text-dark ms-1"><?=count($paymentRows)?></span><?php endif; ?>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-paid">Lunas</button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-rejected">
        Ditolak
        <?php if(count($rejectedUsers)): ?><span class="badge bg-secondary ms-1"><?=count($rejectedUsers)?></span><?php endif; ?>
    </button>
  </li>
</ul>

<div class="tab-content bg-white p-3 border rounded shadow-sm">

<div class="tab-pane fade show active" id="tab-new">
    <table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Order Code</th>
            <th>Nama</th>
            <th>Email</th>
            <th class="text-end" width="200">Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if(empty($newRegs)): ?>
        <tr><td colspan="4" class="text-center py-4 text-muted">Tidak ada registrasi baru.</td></tr>
    <?php else: ?>
        <?php foreach($newRegs as $r): ?>
        <tr>
            <td><span class="badge bg-secondary"><?=htmlspecialchars($r['order_code'])?></span></td>
            <td><?=htmlspecialchars($r['name'])?></td>
            <td><?=htmlspecialchars($r['email'])?></td>
            <td class="text-end">
                <button type="button" class="btn btn-info btn-xs text-white me-1 btn-view-user"
                    data-name="<?=htmlspecialchars($r['name'])?>"
                    data-email="<?=htmlspecialchars($r['email'])?>"
                    data-phone="<?=htmlspecialchars($r['phone'])?>"
                    data-handicap="<?=htmlspecialchars($r['handicap'])?>"
                    data-institution="<?=htmlspecialchars($r['institution'])?>"
                    data-size="<?=htmlspecialchars($r['size'])?>">
                    Detail
                </button>

                <form method="post" action="approve_registration.php" style="display:inline-block;"
                    onsubmit="return confirm('Yakin approve <?=htmlspecialchars($r['name'])?>?');">
                    <input type="hidden" name="user_id" value="<?=$r['user_id']?>">
                    <input type="hidden" name="order_id" value="<?=$r['order_id']?>">
                    <button class="btn btn-success btn-xs">Approve</button>
                </form>
            </td>
        </tr>
        <?php endforeach ?>
    <?php endif; ?>
    </tbody>
    </table>
</div>

<div class="tab-pane fade" id="tab-approved">
    
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 text-primary">Waiting for Payment</h5>
        <a href="export_approved.php" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
    </div>

    <table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Order Code</th>
            <th>Nama</th>
            <th>Email</th>
            <th class="text-end" width="200">Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if(empty($approvedRegs)): ?>
        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada data approved.</td></tr>
    <?php else: ?>
        <?php foreach($approvedRegs as $r): ?>
            <?php
            // --- LOGIKA WA CUSTOM ---
            // 1. Bersihkan nomor dari karakter aneh
            $hp = preg_replace('/[^0-9]/', '', $r['phone']);
            
            // 2. Ubah 08 jadi 628
            if(substr($hp, 0, 1) == '0') {
                $hp = '62' . substr($hp, 1);
            }
            
            // 3. Siapkan Link Upload Dynamic
            $uploadLink = "http://iimsgolftournament.com/payment.php?order=" . $r['order_code'];
            
            // 4. Siapkan Pesan (Sesuai Request)
            $msg = "Yth. Bapak/Ibu *" . $r['name'] . "*,\n\n" .
                   "Terima kasih telah melakukan pendaftaran sebagai peserta *IIMS Golf Tournament 2026*. Kami mengapresiasi antusiasme serta partisipasi Bapak/Ibu dalam mengikuti turnamen ini.\n\n" .
                   "Sebagai tindak lanjut proses registrasi, kami informasikan bahwa biaya pendaftaran sebesar *Rp1.800.000* mohon dapat segera dilakukan pembayaran paling lambat *24 jam* sejak pesan ini diterima melalui rekening berikut:\n\n" .
                   "Bank Danamon\n" .
                   "No. Rekening: *007710077772*\n" .
                   "a.n. Dyandra Promosindo\n\n" .
                   "Apabila hingga batas waktu tersebut kami belum menerima konfirmasi pembayaran, maka pendaftaran akan kami anggap batal atau peserta mengundurkan diri dari IIMS Golf Tournament 2026.\n\n" .
                   "Setelah melakukan pembayaran, mohon kesediaan Bapak/Ibu untuk mengirimkan atau mengunggah bukti transfer sebagai bagian dari proses verifikasi keikutsertaan melalui tautan berikut:\n\n" .
                   "*Upload Bukti Pembayaran*\n" .
                   "$uploadLink\n\n" .
                   "Selain melalui tautan tersebut, bukti pembayaran juga dapat dikirimkan dengan membalas pesan ini secara langsung.\n\n" .
                   "Apabila Bapak/Ibu membutuhkan informasi lebih lanjut, silakan menghubungi kami melalui:\n" .
                   "Email: contact@iimsgolftournament.com\n" .
                   "WhatsApp: 087890764125\n" .
                   "Instagram: @iimsgolftournament\n\n" .
                   "Hormat kami,\n" .
                   "*Panitia IIMS Golf Tournament 2026*\n" .
                   "Dyandra Promosindo";
            
            $linkWa = "https://wa.me/" . $hp . "?text=" . urlencode($msg);
            ?>

        <tr>
            <td><span class="badge bg-primary"><?=htmlspecialchars($r['order_code'])?></span></td>
            <td><?=htmlspecialchars($r['name'])?></td>
            <td><?=htmlspecialchars($r['email'])?></td>
            <td class="text-end">
                <a href="<?=$linkWa?>" target="_blank" class="btn btn-success btn-xs text-white me-1" title="Kirim Notif WA">
                    <i class="bi bi-whatsapp"></i> WA
                </a>

                <button type="button" class="btn btn-info btn-xs text-white btn-view-user"
                    data-name="<?=htmlspecialchars($r['name'])?>"
                    data-email="<?=htmlspecialchars($r['email'])?>"
                    data-phone="<?=htmlspecialchars($r['phone'])?>"
                    data-handicap="<?=htmlspecialchars($r['handicap'])?>"
                    data-institution="<?=htmlspecialchars($r['institution'])?>"
                    data-size="<?=htmlspecialchars($r['size'])?>">
                    Detail
                </button>
            </td>
        </tr>
        <?php endforeach ?>
    <?php endif; ?>
    </tbody>
    </table>
</div>

<div class="tab-pane fade" id="tab-payment">
    <table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Order Code</th>
            <th>Nama</th>
            <th>Email</th>
            <th class="text-end" width="200">Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if(empty($paymentRows)): ?>
        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada pembayaran masuk.</td></tr>
    <?php else: ?>
        <?php foreach($paymentRows as $r): ?>
        <?php 
            $imgUrl = buildPublicFileUrl($r['file_path'], $config);
            $reviewLink = "review.php?payment_id=" . $r['payment_id'];
        ?>
        <tr>
            <td><span class="badge bg-warning text-dark"><?=htmlspecialchars($r['order_code'])?></span></td>
            <td><?=htmlspecialchars($r['name'])?></td>
            <td><?=htmlspecialchars($r['email'])?></td>
            <td class="text-end">
                <button type="button" class="btn btn-info btn-xs text-white me-1 btn-view-user"
                    data-name="<?=htmlspecialchars($r['name'])?>"
                    data-email="<?=htmlspecialchars($r['email'])?>"
                    data-phone="<?=htmlspecialchars($r['phone'])?>"
                    data-handicap="<?=htmlspecialchars($r['handicap'])?>"
                    data-institution="<?=htmlspecialchars($r['institution'])?>"
                    data-size="<?=htmlspecialchars($r['size'])?>">
                    Detail
                </button>

                <a href="<?=$reviewLink?>" class="btn btn-primary btn-xs">Proses</a>
            </td>
        </tr>
        <?php endforeach ?>
    <?php endif; ?>
    </tbody>
    </table>
</div>

<div class="tab-pane fade" id="tab-paid">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0">Data Peserta Lunas</h5>
        <a href="export_paid.php" class="btn btn-success btn-sm">Export Excel</a>
    </div>

    <table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Order Code</th>
            <th>Nama</th>
            <th>Email</th>
            <th class="text-end" width="200">Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if(empty($paidUsers)): ?>
        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada data lunas.</td></tr>
    <?php else: ?>
        <?php foreach($paidUsers as $r): ?>
        <tr>
            <td><span class="badge bg-success"><?=htmlspecialchars($r['order_code'])?></span></td>
            <td><?=htmlspecialchars($r['name'])?></td>
            <td><?=htmlspecialchars($r['email'])?></td>
            <td class="text-end">
                <button type="button" class="btn btn-info btn-xs text-white btn-view-user"
                    data-name="<?=htmlspecialchars($r['name'])?>"
                    data-email="<?=htmlspecialchars($r['email'])?>"
                    data-phone="<?=htmlspecialchars($r['phone'])?>"
                    data-handicap="<?=htmlspecialchars($r['handicap'])?>"
                    data-institution="<?=htmlspecialchars($r['institution'])?>"
                    data-size="<?=htmlspecialchars($r['size'])?>">
                    Detail
                </button>
            </td>
        </tr>
        <?php endforeach ?>
    <?php endif; ?>
    </tbody>
    </table>
</div>

<div class="tab-pane fade" id="tab-rejected">
    <div class="alert alert-light border mb-3 text-muted small">
        <strong>Info:</strong> Daftar ini berisi peserta yang bukti pembayarannya ditolak (Rejected).
    </div>
    <table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Order Code</th>
            <th>Nama</th>
            <th>Email</th>
            <th class="text-end" width="200">Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if(empty($rejectedUsers)): ?>
        <tr><td colspan="4" class="text-center py-4 text-muted">Tidak ada data ditolak.</td></tr>
    <?php else: ?>
        <?php foreach($rejectedUsers as $r): ?>
        <tr>
            <td><span class="badge bg-danger"><?=htmlspecialchars($r['order_code'])?></span></td>
            <td><?=htmlspecialchars($r['name'])?></td>
            <td><?=htmlspecialchars($r['email'])?></td>
            <td class="text-end">
                <button type="button" class="btn btn-info btn-xs text-white btn-view-user"
                    data-name="<?=htmlspecialchars($r['name'])?>"
                    data-email="<?=htmlspecialchars($r['email'])?>"
                    data-phone="<?=htmlspecialchars($r['phone'])?>"
                    data-handicap="<?=htmlspecialchars($r['handicap'])?>"
                    data-institution="<?=htmlspecialchars($r['institution'])?>"
                    data-size="<?=htmlspecialchars($r['size'])?>">
                    Detail
                </button>
                <span class="badge bg-danger ms-2">REJECTED</span>
            </td>
        </tr>
        <?php endforeach ?>
    <?php endif; ?>
    </tbody>
    </table>
</div>

</div></div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Data Peserta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered mb-0">
            <tr><th width="35%" class="bg-light">Nama</th><td id="m-name"></td></tr>
            <tr><th class="bg-light">Email</th><td id="m-email"></td></tr>
            <tr><th class="bg-light">No HP</th><td id="m-phone"></td></tr>
            <tr><th class="bg-light">Handicap</th><td id="m-handicap"></td></tr>
            <tr><th class="bg-light">Instansi</th><td id="m-inst"></td></tr>
            <tr><th class="bg-light">Size Baju</th><td id="m-size"></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Bukti Transfer: <span id="p-order"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center bg-light">
        <img src="" id="paymentProofImg" alt="Bukti Transfer">
        <div class="mt-2 text-muted small">Jika gambar tidak muncul, format mungkin PDF.</div>
      </div>
      <div class="modal-footer">
          <a href="#" id="btn-download" target="_blank" class="btn btn-primary btn-sm">Buka / Download File Asli</a>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. HANDLER MODAL USER
    var userModalEl = document.getElementById('userModal');
    var userModal = new bootstrap.Modal(userModalEl);
    
    document.querySelectorAll('.btn-view-user').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('m-name').textContent = this.getAttribute('data-name');
            document.getElementById('m-email').textContent = this.getAttribute('data-email');
            document.getElementById('m-phone').textContent = this.getAttribute('data-phone');
            document.getElementById('m-handicap').textContent = this.getAttribute('data-handicap');
            document.getElementById('m-inst').textContent = this.getAttribute('data-institution');
            document.getElementById('m-size').textContent = this.getAttribute('data-size');
            userModal.show();
        });
    });

    // 2. HANDLER MODAL PAYMENT
    var payModalEl = document.getElementById('paymentModal');
    var payModal = new bootstrap.Modal(payModalEl);

    document.querySelectorAll('.btn-view-payment').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var imgUrl = this.getAttribute('data-img');
            var orderCode = this.getAttribute('data-order');

            document.getElementById('p-order').textContent = orderCode;
            document.getElementById('paymentProofImg').src = imgUrl;
            document.getElementById('btn-download').href = imgUrl;
            
            payModal.show();
        });
    });
});
</script>
</body>
</html>