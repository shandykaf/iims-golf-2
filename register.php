<?php
/**
 * register.php
 * GET  → tampilkan form
 * POST → proses registrasi, email admin, redirect thankyou
 */

session_start();

// ===============================
// LOAD DEPENDENCIES
// ===============================
$config = require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/email.php';

// Inisialisasi variabel agar tidak error "Undefined variable"
$error = [];
$old   = [
    'name'        => '',
    'email'       => '',
    'phone'       => '',
    'handicap'    => '',
    'institution' => '',
    'size'        => ''
];

// ===============================
// HANDLE SUBMIT (POST)
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ambil input
    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $handicap    = trim($_POST['handicap'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    $size        = trim($_POST['size'] ?? '');

    // Simpan data input ke $old agar form tidak kosong jika ada error
    $old = [
        'name'        => $name,
        'email'       => $email,
        'phone'       => $phone,
        'handicap'    => $handicap,
        'institution' => $institution,
        'size'        => $size
    ];

    // validasi server-side (SEMUA WAJIB DIISI)
    if ($name === '')        $error['name']        = 'Nama wajib diisi.';
    if ($email === '')       $error['email']       = 'Email wajib diisi.';
    if ($phone === '')       $error['phone']       = 'Nomor HP wajib diisi.';
    if ($handicap === '')    $error['handicap']    = 'Handicap wajib diisi.';
    if ($institution === '') $error['institution'] = 'Instansi wajib diisi.';
    if ($size === '')        $error['size']        = 'Ukuran baju wajib dipilih.';
    
    // Validasi Format Email
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error['email'] = 'Format email tidak valid.';
    }

    // Jika tidak ada error, proses ke database
    if (empty($error)) {
        try {
            // ===============================
            // DB TRANSACTION
            // ===============================
            $pdo->beginTransaction();

            // INSERT USERS
            $stmt = $pdo->prepare("
                INSERT INTO users
                    (name, email, phone, handicap, institution, size, created_at)
                VALUES
                    (:name, :email, :phone, :handicap, :institution, :size, NOW())
            ");
            $stmt->execute([
                ':name'        => $name,
                ':email'       => $email,
                ':phone'       => $phone,
                ':handicap'    => $handicap,
                ':institution' => $institution,
                ':size'        => $size,
            ]);

            $userId = $pdo->lastInsertId();

            // INSERT ORDERS
            $orderCode = 'IIMS-' . date('Ymd') . '-' . str_pad($userId, 6, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO orders
                    (user_id, order_code, amount, status, created_at)
                VALUES
                    (:user_id, :order_code, :amount, 'pending', NOW())
            ");
            $stmt->execute([
                ':user_id'    => $userId,
                ':order_code' => $orderCode,
                ':amount'     => 0,
            ]);

            $pdo->commit();

            // ===============================
            // EMAIL ADMIN (ONLY)
            // ===============================
            sendAdminNewRegistration([
                'name'        => $name,
                'email'       => $email,
                'phone'       => $phone,
                'handicap'    => $handicap,
                'institution' => $institution,
            ]);

            // ===============================
            // REDIRECT THANK YOU
            // ===============================
            header('Location: thankyou.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $error['server'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

// ===============================
// GET REQUEST → TAMPILKAN FORM
// ===============================
if (isset($_GET['error']) && $_GET['error'] === 'server' && empty($error['server'])) {
    $error['server'] = 'Terjadi kesalahan server.';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Pendaftaran — <?= htmlspecialchars($siteTitle ?? 'IIMS Golf') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Spline+Sans:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/register.css">
  <style>
    .sr-only{ position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }
  </style>
</head>
<body>

<header class="register-header">
  <img src="<?= htmlspecialchars($logo ?? 'assets/img/img-logo-black.png') ?>" class="reg-logo" alt="<?=htmlspecialchars($siteTitle ?? '')?>">
</header>

<section class="register-section">
  <h1 class="register-title">PENDAFTARAN</h1>

  <?php if (!empty($error['server'])): ?>
    <div class="error-msg" style="margin-bottom:12px; text-align:center;"><?=htmlspecialchars($error['server'])?></div>
  <?php endif; ?>

  <form method="post" id="regForm" class="reg-form" novalidate>

    <div class="input-group full floating <?= isset($error['name']) ? 'has-error' : '' ?>">
      <input name="name" type="text" placeholder=" " value="<?=htmlspecialchars($old['name'])?>" required>
      <label>Nama Lengkap</label>
      <?php if(isset($error['name'])): ?><p class="error-msg"><?=htmlspecialchars($error['name'])?></p><?php endif; ?>
    </div>

    <div class="input-group half floating <?= isset($error['phone']) ? 'has-error' : '' ?>">
      <input name="phone" type="tel" placeholder=" " value="<?=htmlspecialchars($old['phone'])?>" required>
      <label>Nomor HP (WhatsApp)</label>
      <?php if(isset($error['phone'])): ?><p class="error-msg"><?=htmlspecialchars($error['phone'])?></p><?php endif; ?>
    </div>

    <div class="input-group half floating <?= isset($error['email']) ? 'has-error' : '' ?>">
      <input name="email" type="email" placeholder=" " value="<?=htmlspecialchars($old['email'])?>" required>
      <label>Email</label>
      <?php if(isset($error['email'])): ?><p class="error-msg"><?=htmlspecialchars($error['email'])?></p><?php endif; ?>
    </div>

    <div class="input-group half floating <?= isset($error['handicap']) ? 'has-error' : '' ?>">
      <input name="handicap" type="text" placeholder=" " value="<?=htmlspecialchars($old['handicap'])?>" required>
      <label>Handicap</label>
      <?php if(isset($error['handicap'])): ?><p class="error-msg"><?=htmlspecialchars($error['handicap'])?></p><?php endif; ?>
    </div>  

    <div class="input-group half floating <?= isset($error['institution']) ? 'has-error' : '' ?>">
      <input name="institution" type="text" placeholder=" " value="<?=htmlspecialchars($old['institution'])?>" required>
      <label>Instansi / Komunitas</label>
      <?php if(isset($error['institution'])): ?><p class="error-msg"><?=htmlspecialchars($error['institution'])?></p><?php endif; ?>
    </div>

    <div class="field-size input-group full <?= isset($error['size']) ? 'has-error' : '' ?>">
      <label class="sr-only" for="size">Pilih Ukuran</label>
      <select id="size" name="size" required>
        <option value="">Pilih ukuran</option>
        <?php foreach (['Man - XS','Man - S','Man - M','Man - L','Man - XL','Man - XXL','Man - XXXL'] as $s): ?>
          <option value="<?=htmlspecialchars($s)?>" <?=($old['size'] === $s ? 'selected' : '')?>><?=htmlspecialchars($s)?></option>
        <?php endforeach; ?>
      </select>

      <div class="size-row notes">
        <div class="size-note">Ukuran mengacu pada standar size chart umum. Pastikan Anda memilih ukuran yang paling nyaman untuk digunakan saat event.</div>
        <a class="size-link" href="/assets/img/img-chart_size.jpg" target="_blank">LIHAT DETAIL UKURAN</a>
      </div>

      <?php if(isset($error['size'])): ?><p class="error-msg"><?=htmlspecialchars($error['size'])?></p><?php endif; ?>
    </div>

    <div class="input-group full form-submit">
      <button type="submit" class="btn-submit">DAFTAR SEKARANG</button>
    </div>
  </form>
</section>

<script>
/* Floating label initial state and toggling */
document.querySelectorAll('.floating input, .floating textarea').forEach(inp => {
  if (inp.value.trim() !== '') inp.classList.add('has-value');
  inp.addEventListener('input', () => inp.classList.toggle('has-value', inp.value.trim() !== ''));
});

/* client-side validation: Strict (All Required) */
const form = document.getElementById('regForm');
form.addEventListener('submit', function(e){
  // clear previous client-side error messages
  document.querySelectorAll('.input-group .error-msg.client').forEach(n => n.remove());
  document.querySelectorAll('.input-group.has-error').forEach(n => n.classList.remove('has-error'));

  let hasErr = false;

  // LIST SEMUA FIELD YANG WAJIB DIISI
  const required = ['name', 'phone', 'email', 'handicap', 'institution'];
  
  required.forEach(name => {
    const el = form.querySelector(`[name="${name}"]`);
    if (el && el.value.trim() === '') {
      addClientError(el, 'Field ini wajib diisi');
      hasErr = true;
    }
  });

  // Validasi Email Format
  const emailVal = form.querySelector('[name="email"]').value.trim();
  if (emailVal !== '' && !/^\S+@\S+\.\S+$/.test(emailVal)) {
    addClientError(form.querySelector('[name="email"]'), 'Format email tidak valid.');
    hasErr = true;
  }

  // Validasi Dropdown Size
  if (form.size.value === '') {
    addClientError(form.size, 'Pilih ukuran baju.');
    hasErr = true;
  }

  if (hasErr) e.preventDefault();
});

function addClientError(inputEl, message) {
  const group = inputEl.closest('.input-group');
  if (!group) return;
  group.classList.add('has-error');
  const p = document.createElement('p');
  p.className = 'error-msg client';
  p.textContent = message;
  group.appendChild(p);
  // ensure label floats if input has value (so error does not overlap)
  if (inputEl.tagName.toLowerCase() !== 'select') {
    inputEl.classList.toggle('has-value', inputEl.value.trim() !== '');
  }
}
</script>
</body>
</html>