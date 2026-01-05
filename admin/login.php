<?php
// admin/login.php
session_start();

// load config (object) and db
require __DIR__ . '/../includes/config.php';
$config = $config ?? null;
require __DIR__ . '/../includes/db.php';

// helper: only allow relative next paths to avoid open redirect
function rel_path($path) {
    if (!$path) return null;
    if (strpos($path, '//') !== false || preg_match('#^https?://#i', $path)) return null;
    // strip leading domain-like things and ensure starts with /
    $p = $path;
    // allow relative paths like admin/review.php or /admin/review.php
    if ($p && $p[0] !== '/') $p = '/' . ltrim($p, '/');
    return $p;
}

// Determine next redirect target
$next = rel_path($_GET['next'] ?? ($_POST['next'] ?? ''));

// If there is a payment_id param in GET (e.g. redirected link), and no explicit next, use review page
if (empty($next) && !empty($_GET['payment_id'])) {
    $pid = (int)$_GET['payment_id'];
    if ($pid > 0) $next = '/admin/review.php?payment_id=' . $pid;
}

// if already logged in -> redirect to next or dashboard
if (!empty($_SESSION['admin']) || !empty($_SESSION['admin_logged_in'])) {
    $redirectTo = $next ?: '/admin/dashboard.php';
    header('Location: ' . $redirectTo);
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_csrf)) {
        $err = 'Invalid request (CSRF).';
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        if ($user === '' || $pass === '') {
            $err = 'Username & password diperlukan.';
        } else {
            try {
                // lookup admin in DB
                $stmt = $pdo->prepare("SELECT id, username, password_hash, api_token, email FROM admins WHERE username = ? LIMIT 1");
                $stmt->execute([$user]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && !empty($admin['password_hash']) && password_verify($pass, $admin['password_hash'])) {
                    // login success
                    // Keep existing session structure (backwards compatibility)
                    $_SESSION['admin'] = [
                        'id' => (int)$admin['id'],
                        'username' => $admin['username'],
                        'email' => $admin['email'] ?? null,
                        'login_at' => date('Y-m-d H:i:s')
                    ];

                    // Also set flags that other scripts (review.php variants) may expect
                    $_SESSION['admin_id'] = (int)$admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_logged_in'] = true;

                    // redirect to next (safe)
                    $redirectTo = $next ?: '/admin/dashboard.php';
                    header('Location: ' . $redirectTo);
                    exit;
                } else {
                    $err = 'Username atau password salah.';
                }
            } catch (Exception $e) {
                error_log('admin/login.php DB error: ' . $e->getMessage());
                $err = 'Terjadi kesalahan server. Cek log.';
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style> body{font-family:Arial,Helvetica,sans-serif;background:#f7f7f7} </style>
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <h3 class="mb-3">Admin Login</h3>

      <?php if ($err): ?>
        <div class="alert alert-danger"><?=htmlspecialchars($err)?></div>
      <?php endif; ?>

      <form method="post" class="card card-body">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
        <input type="hidden" name="next" value="<?=htmlspecialchars($next ?: '')?>">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input name="password" type="password" class="form-control" required>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <button class="btn btn-primary">Login</button>
          <?php if ($next): ?>
            <small class="text-muted">Setelah login akan diarahkan ke: <?=htmlspecialchars($next)?></small>
          <?php endif; ?>
        </div>
      </form>

      <p class="mt-3 text-muted small">Gunakan akun admin yang terdaftar di tabel <code>admins</code>.</p>
    </div>
  </div>
</div>
</body>
</html>
