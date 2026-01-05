<?php
// admin/doorprizes.php
// Final: upload ALWAYS to DOCUMENT_ROOT/uploads/doorprizes
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    $cur = $_SERVER['REQUEST_URI'] ?? '/admin/doorprizes.php';
    header('Location: login.php?next=' . urlencode($cur));
    exit;
}

require __DIR__ . '/../includes/db.php';
$config = require __DIR__ . '/../includes/config.php';

// helpers
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function log_admin($msg) { @file_put_contents(sys_get_temp_dir() . '/admin_doorprizes.log', "[".date('c')."] ".$msg."\n", FILE_APPEND | LOCK_EX); }
function getSiteUrl() {
    global $config;
    $site = '';
    if (is_array($config) && !empty($config['site_url'])) $site = rtrim($config['site_url'], '/');
    if (is_object($config) && !empty($config->site_url)) $site = rtrim($config->site_url, '/');
    if ($site === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $site;
}
function buildPublicFileUrl($fname) {
    if (!$fname) return '';
    return getSiteUrl() . '/uploads/doorprizes/' . ltrim($fname, '/');
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

// prefer POST action over GET
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

// --- FORCE physical upload directory to DOCUMENT_ROOT/uploads/doorprizes ---
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
$physicalDir = $docRoot . '/uploads/doorprizes';

// ensure directory exists
if (!is_dir($physicalDir)) {
    if (!@mkdir($physicalDir, 0755, true) && !is_dir($physicalDir)) {
        log_admin("Failed to create directory: {$physicalDir}");
    } else {
        log_admin("Created directory: {$physicalDir}");
    }
}

// ensure writable
if (!is_writable($physicalDir)) {
    @chmod($physicalDir, 0755);
    if (!is_writable($physicalDir)) {
        log_admin("Directory not writable after chmod: {$physicalDir}");
    }
}

// variables
$flash = '';
$error = '';
$editing = false;
$editRow = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $posted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted)) {
        $error = 'Invalid CSRF token.';
    } else {
        if ($action === 'save' && !empty($_POST['title'])) {
            $title = trim($_POST['title'] ?? '');
            $qty = max(1, (int)($_POST['qty'] ?? 1));

            // image handling
            $imageName = null;
            if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $f = $_FILES['image'];

                // upload error check
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $error = "Upload error code: " . $f['error'];
                    log_admin($error);
                } else {
                    // validate mime via finfo
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $f['tmp_name']);
                    finfo_close($finfo);
                    $allow = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];

                    if (!isset($allow[$mime])) {
                        $error = 'Tipe gambar tidak diizinkan. Detected: ' . $mime;
                        log_admin($error);
                    } else {
                        $ext = $allow[$mime];
                        try { $basename = 'dp_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext; }
                        catch (Exception $e) { $basename = 'dp_' . time() . '_' . substr(md5(uniqid('', true)),0,12) . '.' . $ext; }
                        $dest = rtrim($physicalDir, '/') . '/' . $basename;

                        if (!move_uploaded_file($f['tmp_name'], $dest)) {
                            $error = 'Gagal memindahkan file gambar ke: ' . $dest;
                            log_admin("move_uploaded_file failed. tmp: {$f['tmp_name']} dest: {$dest}");
                        } else {
                            @chmod($dest, 0644);
                            $imageName = $basename;
                            log_admin("Image uploaded OK -> {$dest}");
                        }
                    }
                }
            } else {
                log_admin("No file uploaded (UPLOAD_ERR_NO_FILE or no field present).");
            }

            if (!$error) {
                try {
                    if ($id > 0) {
                        // update existing
                        if ($imageName !== null) {
                            // remove old image if exists
                            $stmtOld = $pdo->prepare("SELECT image FROM doorprizes WHERE id = ?");
                            $stmtOld->execute([$id]);
                            $old = $stmtOld->fetchColumn();
                            if ($old) {
                                @unlink(rtrim($physicalDir, '/') . '/' . $old);
                                log_admin("Removed old image: " . rtrim($physicalDir, '/') . '/' . $old);
                            }
                            $stmt = $pdo->prepare("UPDATE doorprizes SET title = ?, qty = ?, image = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$title, $qty, $imageName, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE doorprizes SET title = ?, qty = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$title, $qty, $id]);
                        }
                        header('Location: /admin/doorprizes.php?msg=' . urlencode('Doorprize diperbarui'));
                        exit;
                    } else {
                        // insert. category_id required -> default 1
                        $catId = 1;
                        $stmt = $pdo->prepare("INSERT INTO doorprizes (category_id, title, qty, image, notes, created_at) VALUES (?, ?, ?, ?, NULL, NOW())");
                        $stmt->execute([$catId, $title, $qty, $imageName]);
                        header('Location: /admin/doorprizes.php?msg=' . urlencode('Doorprize ditambahkan'));
                        exit;
                    }
                } catch (Exception $e) {
                    $error = 'DB error: ' . $e->getMessage();
                    log_admin($error);
                }
            }
        } elseif ($action === 'delete' && $id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT image FROM doorprizes WHERE id = ?");
                $stmt->execute([$id]);
                $img = $stmt->fetchColumn();
                $stmt = $pdo->prepare("DELETE FROM doorprizes WHERE id = ?");
                $stmt->execute([$id]);
                if ($img) {
                    @unlink(rtrim($physicalDir, '/') . '/' . $img);
                    log_admin("Deleted image file for removed doorprize: {$img}");
                }
                header('Location: /admin/doorprizes.php?msg=' . urlencode('Doorprize dihapus'));
                exit;
            } catch (Exception $e) {
                $error = 'DB error: ' . $e->getMessage();
                log_admin($error);
            }
        } else {
            $error = 'Invalid request.';
        }
    }
}

// prepare edit data if requested (GET action=edit)
if ($action === 'edit' && $id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM doorprizes WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($editRow) $editing = true;
    } catch (Exception $e) {
        $error = 'DB error: ' . $e->getMessage();
        log_admin($error);
    }
}

// list view (always)
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;
try {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM doorprizes")->fetchColumn();
    $stmt = $pdo->prepare("SELECT * FROM doorprizes ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $listRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $listRows = [];
    $total = 0;
    log_admin("Fetch doorprizes error: " . $e->getMessage());
}
$totalPages = max(1, ceil($total / $limit));
$adminName = $_SESSION['admin_username'] ?? ($_SESSION['admin']['username'] ?? 'Admin');
$msg = $_GET['msg'] ?? '';

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Admin - Doorprize</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#f9fafc; }
  .navbar-transparent { background: transparent !important; box-shadow:none; }
  .card { border-radius:10px; }
  .thumb { width:96px; height:72px; object-fit:cover; border-radius:6px; border:1px solid #eee; }
  .action-group .btn { margin-left:6px; margin-right:6px; }
  @media (max-width:575px){ .action-group .btn{ display:block; margin:6px 0; } }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-transparent">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="/admin/dashboard.php">Admin Panel</a>
    <div class="collapse navbar-collapse" id="navArea">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="/admin/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active" href="/admin/doorprizes.php">Doorprize</a></li>
      </ul>
      <span class="navbar-text me-3">Logged in as: <strong><?=e($adminName)?></strong></span>
      <a href="/admin/logout.php" class="btn btn-outline-dark btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container" style="max-width:1100px;margin-top:26px;margin-bottom:60px">

  <?php if ($msg): ?>
    <div class="alert alert-success"><?=e($msg)?></div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="alert alert-success"><?=e($flash)?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?=e($error)?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-md-5">
      <div class="card p-3 shadow-sm">
        <h5 class="mb-3"><?= $editing ? 'Edit Doorprize' : 'Tambah Doorprize' ?></h5>

        <form method="post" enctype="multipart/form-data" class="mb-0">
          <input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
          <input type="hidden" name="action" value="save">
          <?php if ($editing): ?><input type="hidden" name="id" value="<?=e($editRow['id'])?>"><?php endif; ?>

          <div class="mb-2">
            <label class="form-label">Hadiah (title)</label>
            <input name="title" class="form-control" required value="<?=e($editing ? $editRow['title'] : '')?>">
          </div>

          <div class="mb-2">
            <label class="form-label">Jumlah (qty)</label>
            <input name="qty" type="number" class="form-control" min="1" required value="<?=e($editing ? $editRow['qty'] : 1)?>">
          </div>

          <div class="mb-2">
            <label class="form-label">Gambar (optional)</label>
            <?php if ($editing && !empty($editRow['image'])): ?>
              <div class="mb-2"><img src="<?=e(buildPublicFileUrl($editRow['image']))?>" alt="thumb" class="thumb"></div>
            <?php endif; ?>
            <input name="image" type="file" accept="image/*" class="form-control">
            <div class="form-text">Format: jpg/png/gif, ukuran disarankan &lt; 2MB.</div>
          </div>

          <div class="d-flex">
            <button class="btn btn-primary me-2"><?= $editing ? 'Simpan Perubahan' : 'Tambah Doorprize' ?></button>
            <?php if ($editing): ?>
              <a href="/admin/doorprizes.php" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="col-md-7">
      <div class="card p-3 shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Daftar Doorprize</h5>
          <div class="small text-muted">Total: <?=number_format($total)?></div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>#</th>
                <th>Gambar</th>
                <th>Hadiah</th>
                <th>Qty</th>
                <th>Created</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($listRows)): ?>
                <tr><td colspan="6" class="text-center text-muted">Belum ada doorprize.</td></tr>
              <?php else: foreach ($listRows as $i => $r): ?>
                <tr>
                  <td><?=e($offset + $i + 1)?></td>
                  <td>
                    <?php if (!empty($r['image'])): ?>
                      <img src="<?=e(buildPublicFileUrl($r['image']))?>" class="thumb" alt="">
                    <?php else: ?>
                      <span class="text-muted small">-</span>
                    <?php endif; ?>
                  </td>
                  <td><?=e($r['title'])?></td>
                  <td><?=e($r['qty'])?></td>
                  <td><?=e($r['created_at'])?></td>
                  <td class="text-end">
                    <div class="action-group d-inline-block">
                      <a class="btn btn-sm btn-outline-primary" href="/admin/doorprizes.php?action=edit&id=<?=e($r['id'])?>">Edit</a>

                      <!-- tombol Pilih Pemenang mengarah ke root /pilih_pemenang.php -->
                      <a class="btn btn-sm btn-success" href="/pilih_pemenang.php?doorprize_id=<?=e($r['id'])?>">Pilih Pemenang</a>

                      <form method="post" style="display:inline" onsubmit="return confirm('Hapus doorprize ini?');">
                        <input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?=e($r['id'])?>">
                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <!-- pagination -->
        <nav class="mt-3" aria-label="Page navigation">
          <ul class="pagination">
            <?php if ($page > 1): ?>
              <li class="page-item"><a class="page-link" href="/admin/doorprizes.php?page=<?=$page-1?>">Prev</a></li>
            <?php else: ?>
              <li class="page-item disabled"><span class="page-link">Prev</span></li>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <li class="page-item <?= $p===$page ? 'active' : '' ?>"><a class="page-link" href="/admin/doorprizes.php?page=<?=$p?>"><?=$p?></a></li>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
              <li class="page-item"><a class="page-link" href="/admin/doorprizes.php?page=<?=$page+1?>">Next</a></li>
            <?php else: ?>
              <li class="page-item disabled"><span class="page-link">Next</span></li>
            <?php endif; ?>
          </ul>
        </nav>

      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
