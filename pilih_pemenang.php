<?php
// pilih_pemenang.php
// Root UI: daftar hadiah (left), wheel (right), autosave winner.
// Requires: includes/db.php, admin login session, /admin/mark_winner.php endpoint.

session_start();
if (empty($_SESSION['admin_logged_in'])) {
    $cur = $_SERVER['REQUEST_URI'] ?? '/pilih_pemenang.php';
    header('Location: login.php?next=' . urlencode($cur));
    exit;
}

require __DIR__ . '/includes/db.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function log_admin($msg){ @file_put_contents(sys_get_temp_dir().'/pilih_pemenang.log', "[".date('c')."] ".$msg."\n", FILE_APPEND | LOCK_EX); }
function buildPublicFileUrl($fname){
    if (!$fname) return '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme.'://'.$host.'/uploads/doorprizes/'.ltrim($fname,'/');
}

$selected_id = isset($_GET['doorprize_id']) ? (int)$_GET['doorprize_id'] : 0;
$adminName = $_SESSION['admin_username'] ?? ($_SESSION['admin']['username'] ?? 'Admin');
$adminToken = $_SESSION['admin_api_token'] ?? ''; // optional token

// fetch doorprizes + winners_count
try {
    $sql = "SELECT d.*, COALESCE(w.cnt,0) AS winners_count
            FROM doorprizes d
            LEFT JOIN (SELECT doorprize_id, COUNT(*) cnt FROM dp_winners GROUP BY doorprize_id) w
            ON w.doorprize_id = d.id
            ORDER BY d.id ASC";
    $doorprizes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $doorprizes = [];
    $note = "DB Error: " . $e->getMessage();
    log_admin($note);
}

// helper fetch segments
function fetch_segments($pdo, $dpid){
    if ($dpid<=0) return [];
    $sql = "SELECT o.user_id, u.name, u.email, o.id AS order_id, o.order_code
            FROM orders o
            JOIN users u ON u.id = o.user_id
            WHERE o.status='paid'
              AND o.user_id NOT IN (SELECT user_id FROM dp_winners WHERE doorprize_id = ?)
            GROUP BY o.user_id
            ORDER BY RAND()
            LIMIT 500";
    try {
        $stm = $pdo->prepare($sql);
        $stm->execute([$dpid]);
        $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
        $segs = [];
        foreach ($rows as $r) {
            $label = trim(($r['name'] ?: ('#'.$r['user_id'])) . ' — ' . ($r['order_code'] ?? ''));
            $segs[] = ['id'=>(int)$r['user_id'],'label'=>$label,'email'=>$r['email'],'order_id'=>(int)$r['order_id']];
        }
        return $segs;
    } catch (Exception $e) {
        log_admin("fetch_segments error: ".$e->getMessage());
        return [];
    }
}

// AJAX segments endpoint
if (isset($_GET['ajax_segments']) && isset($_GET['doorprize_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $dpid = (int)$_GET['doorprize_id'];
    $segments = fetch_segments($pdo,$dpid);
    $stm = $pdo->prepare("SELECT qty FROM doorprizes WHERE id=? LIMIT 1");
    $stm->execute([$dpid]); $r = $stm->fetch(PDO::FETCH_ASSOC);
    $qty = (int)($r['qty'] ?? 0);
    $stm = $pdo->prepare("SELECT COUNT(*) FROM dp_winners WHERE doorprize_id=?");
    $stm->execute([$dpid]); $cnt = (int)$stm->fetchColumn();
    $remaining = max(0, $qty - $cnt);
    echo json_encode(['segments'=>$segments,'remaining'=>$remaining], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

// preload selection
$selectedPrize = null;
$initialSegments = [];
if ($selected_id>0) {
    foreach ($doorprizes as $d) if ((int)$d['id'] === $selected_id) { $selectedPrize = $d; break; }
    if ($selectedPrize) $initialSegments = fetch_segments($pdo, $selected_id);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Doorprize — Pilih Pemenang</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{ --accent:#e73b3b; --muted:#6c757d; --panel:#fbfdff; }
body{ font-family:system-ui, -apple-system, "Segoe UI", Roboto, Arial; background:#f4f6f8; margin:0; }
.container-main{ max-width:1200px; margin:28px auto; padding:18px; }
.panel{ background:#fff; border-radius:18px; padding:24px; box-shadow:0 12px 36px rgba(16,24,40,0.06); }
.title{ text-align:center; font-weight:700; margin-bottom:18px; }
.layout{ display:flex; gap:28px; align-items:flex-start; flex-wrap:wrap; }
.left{ flex:0 0 34%; max-width:34%; }
.left-inner{ background:#f8f9fa; padding:18px; border-radius:12px; min-height:320px; }
.prize-card{ display:flex; flex-direction:column; align-items:center; padding:14px; margin-bottom:14px; cursor:pointer; border-radius:12px; border:2px solid transparent; transition:all .14s; background:transparent;}
.img-wrap{ width:160px; height:120px; display:flex; align-items:center; justify-content:center; background:#fff; border-radius:8px; box-shadow:0 6px 18px rgba(20,20,30,0.04); }
.prize-card img{ max-width:100%; max-height:100%; object-fit:contain; }
.prize-card .title{ margin-top:12px; font-weight:700; color:#222; }
.prize-card .meta{ margin-top:8px; color:var(--muted); font-size:0.95rem; }
.prize-card.selected{ border-color:var(--accent); background:#fff; box-shadow:0 12px 30px rgba(231,59,59,0.08); }
.badge-full{ display:inline-block; margin-top:8px; padding:6px 10px; border-radius:12px; background:#fce6e6; color:#8a1d1d; font-weight:700; font-size:12px; }
.prize-card.badge-removed{ opacity:0.7; pointer-events:none; }

/* right */
.right{ flex:1; display:flex; flex-direction:column; align-items:center; }
.pointer{ width:0;height:0;border-left:22px solid transparent;border-right:22px solid transparent;border-bottom:34px solid var(--accent); margin-bottom:8px; }
canvas#wheel{ width:520px; height:520px; border-radius:999px; background:var(--panel); border:10px solid #f0f2f5; box-shadow:0 12px 40px rgba(16,24,40,0.05); }
.controls{ margin-top:18px; }
.btn-spin{ background:var(--accent); color:#fff; border:none; padding:12px 26px; border-radius:28px; font-weight:800; box-shadow:0 12px 30px rgba(231,59,59,0.14); }
.winner-box{ margin-top:18px; width:72%; min-height:72px; background:#fff; padding:14px; border-radius:12px; box-shadow:0 10px 30px rgba(16,24,40,0.05); display:flex; flex-direction:column; align-items:center; justify-content:center; }
.winner-title{ font-weight:700; color:#253; }
.winner-email{ color:var(--muted); margin-top:6px; font-size:.95rem; }
.msg-error{ color:#b02a37; margin-top:8px; }

/* responsive */
@media(max-width:991px){
  .layout{ flex-direction:column; }
  .left{ max-width:100%; }
  canvas#wheel{ width:360px; height:360px; }
  .winner-box{ width:100%; }
}
</style>
</head>
<body>

<div class="container-main">
  <div class="panel">
    <h2 class="title">DOORPRIZE</h2>

    <div class="layout">
      <div class="left">
        <div class="left-inner">
          <?php if (empty($doorprizes)): ?>
            <div class="text-muted">Tidak ada hadiah.</div>
          <?php else: foreach ($doorprizes as $d):
            $id=(int)$d['id']; $title=e($d['title']); $img=$d['image']; $qty=(int)$d['qty'];
            $wcnt=(int)$d['winners_count']; $remaining=max(0,$qty-$wcnt);
            $selClass = ($selected_id===$id) ? 'selected' : '';
          ?>
            <div class="prize-card <?= $selClass ?>" data-id="<?= $id ?>" data-remaining="<?= $remaining ?>">
              <div class="img-wrap">
                <?php if ($img): ?><img src="<?= e(buildPublicFileUrl($img)) ?>" alt="<?= $title ?>"><?php else: ?><div style="color:#999">No Image</div><?php endif; ?>
              </div>
              <div class="title"><?= $title ?></div>
              <?php if ($remaining <= 0): ?>
                <div class="badge-full">Kuota terpenuhi</div>
              <?php else: ?>
                <div class="meta">Sisa: <?= $remaining ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="right">
        <div class="pointer"></div>
        <canvas id="wheel" width="520" height="520"></canvas>

        <div class="controls">
          <button id="btnSpin" class="btn-spin" disabled>🔁 PILIH PEMENANG</button>
        </div>

        <div class="winner-box" id="winnerBox">
          <div id="winnerTitle" class="winner-title">Belum ada pemenang</div>
          <div id="winnerEmail" class="winner-email">Tekan tombol untuk memutar.</div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
const DOORPRIZES = <?= json_encode($doorprizes, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
let SELECTED_ID = <?= json_encode($selected_id) ?>;
let SELECTED_PRIZE = <?= json_encode($selectedPrize ?: null, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
let AR_SEGMENTS = <?= json_encode($initialSegments, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const ADMIN_TOKEN = <?= json_encode($adminToken ?: '') ?>;

(function(){
  const canvas = document.getElementById('wheel');
  const ctx = canvas.getContext('2d');
  const btnSpin = document.getElementById('btnSpin');
  const winnerTitle = document.getElementById('winnerTitle');
  const winnerEmail = document.getElementById('winnerEmail');
  const winnerBox = document.getElementById('winnerBox');

  let segments = AR_SEGMENTS.slice();
  let n = segments.length;
  const cx = canvas.width/2, cy = canvas.height/2;
  const radius = Math.min(cx,cy)-18;
  let spinning = false;

  function drawWheel(rot=0){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    ctx.save();
    ctx.translate(cx,cy);
    ctx.rotate(rot*Math.PI/180);
    const angle = 2*Math.PI/Math.max(1,n);
    for (let i=0;i<n;i++){
      const start = i*angle, end = start+angle;
      const hue = Math.round((i/n)*360);
      ctx.beginPath();
      ctx.moveTo(0,0);
      ctx.arc(0,0,radius,start,end);
      ctx.closePath();
      ctx.fillStyle = `hsl(${hue},66%,60%)`;
      ctx.fill();
      ctx.strokeStyle = '#fff'; ctx.lineWidth = 1; ctx.stroke();

      ctx.save();
      ctx.rotate((start+end)/2);
      ctx.translate(radius*0.62,0);
      ctx.rotate(Math.PI/2);
      wrapText(ctx, segments[i].label, 0, 0, 140, 13);
      ctx.restore();
    }
    ctx.restore();

    // center
    ctx.beginPath(); ctx.arc(cx,cy,50,0,2*Math.PI); ctx.fillStyle='#fff'; ctx.fill(); ctx.lineWidth=2; ctx.strokeStyle='#eee'; ctx.stroke();
  }

  function wrapText(ctx, text, x, y, maxW, lineH){
    ctx.fillStyle = '#111'; ctx.font = '13px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    const words = (text||'').split(' ');
    let line = '', lines = [];
    for (let w of words) {
      const test = line ? (line + ' ' + w) : w;
      if (ctx.measureText(test).width > maxW && line) { lines.push(line); line = w; }
      else line = test;
    }
    if (line) lines.push(line);
    const total = lines.length * lineH; let startY = - (total/2) + (lineH/2);
    for (let i=0;i<lines.length;i++) ctx.fillText(lines[i], x, startY + i*lineH);
  }

  drawWheel(0);

  // prize click
  document.querySelectorAll('.prize-card').forEach(card => {
    card.addEventListener('click', async () => {
      if (card.classList.contains('badge-removed')) return;
      document.querySelectorAll('.prize-card').forEach(c=>c.classList.remove('selected'));
      card.classList.add('selected');
      SELECTED_ID = parseInt(card.dataset.id,10);
      SELECTED_PRIZE = DOORPRIZES.find(p => parseInt(p.id,10) === SELECTED_ID) || null;
      await loadSegmentsFor(SELECTED_ID);
    });
  });

  // loadSegmentsFor with safe json var
  async function loadSegmentsFor(id){
    let json = null;
    try {
      const res = await fetch(`?ajax_segments=1&doorprize_id=${id}`, { credentials:'same-origin' });
      if (!res.ok) {
        try { json = await res.json(); } catch(e){ json = null; }
        segments = [];
      } else {
        json = await res.json();
        segments = json.segments || [];
      }
    } catch (err) {
      console.error('Network error loadSegmentsFor', err);
      segments = []; json = null;
    }
    n = segments.length;
    drawWheel(0);

    const card = document.querySelector('.prize-card[data-id="'+id+'"]');
    if (card && json && typeof json.remaining !== 'undefined') {
      card.dataset.remaining = json.remaining;
      const meta = card.querySelector('.meta');
      if (meta) meta.textContent = 'Sisa: ' + json.remaining;
      if (json.remaining <= 0) markPrizeFull(card);
    }
    updateSpinState();
  }

  function updateSpinState(){
    let can = segments.length > 0;
    const sel = document.querySelector('.prize-card.selected');
    if (!sel) { can = false; }
    else {
      const disabled = sel.classList.contains('badge-removed') || sel.getAttribute('aria-disabled') === 'true';
      const rem = parseInt(sel.dataset.remaining || 0, 10);
      if (disabled || isNaN(rem) || rem <= 0) can = false;
    }
    btnSpin.disabled = !can;
  }

  // keep card visible but disable when full
  function markPrizeFull(card){
    if (!card) return;
    if (!card.querySelector('.badge-full')) {
      const b = document.createElement('div'); b.className = 'badge-full'; b.textContent = 'Kuota terpenuhi'; card.appendChild(b);
    }
    card.classList.add('badge-removed');
    card.setAttribute('aria-disabled','true');
    const meta = card.querySelector('.meta'); if (meta) meta.textContent = 'Sisa: 0';
    // disable spin if this is selected
    const sel = document.querySelector('.prize-card.selected');
    if (sel && sel === card) {
      btnSpin.disabled = true;
      winnerTitle.textContent = 'Kuota hadiah ini telah terpenuhi.';
      winnerEmail.textContent = '';
    }
  }

  // spin
  btnSpin.addEventListener('click', function(){
    if (spinning || segments.length===0) return;
    spinning = true; btnSpin.disabled = true;
    winnerTitle.textContent = 'Memutar...'; winnerEmail.textContent = '';
    const rounds = 6 + Math.floor(Math.random()*4);
    const chosen = Math.floor(Math.random()*segments.length);
    const segAng = 360 / Math.max(1, segments.length);
    const jitter = (Math.random()*segAng)-(segAng/2);
    const target = rounds*360 + (360 - (chosen*segAng + segAng/2)) + jitter;
    const dur = 4200 + Math.floor(Math.random()*1200);
    const start = performance.now();

    function anim(now) {
      const t = Math.min(1, (now - start) / dur);
      const ease = 1 - Math.pow(1 - t, 3);
      drawWheel((target * ease) % 360);
      if (t < 1) requestAnimationFrame(anim);
      else {
        spinning = false;
        const winner = segments[chosen];
        showWinner(winner);
        autosaveWinner(winner);
      }
    }
    requestAnimationFrame(anim);
  });

  function showWinner(w){
    if (!w) { winnerTitle.textContent = 'Tidak ada pemenang'; winnerEmail.textContent = ''; return; }
    winnerTitle.textContent = w.label;
    winnerEmail.textContent = w.email || '';
  }

  // Autosave with diagnostics and optional admin token header
  async function autosaveWinner(w){
    if (!w || !SELECTED_ID) {
      winnerTitle.textContent = 'Gagal menyimpan';
      winnerEmail.textContent = 'Data pemenang tidak valid.';
      return;
    }
    btnSpin.disabled = true;
    const payload = { doorprize_id: SELECTED_ID, user_id: w.id, name: w.label, email: w.email, prize: SELECTED_PRIZE ? SELECTED_PRIZE.title : null };
    try {
      const headers = {'Content-Type':'application/json'};
      if (typeof ADMIN_TOKEN !== 'undefined' && ADMIN_TOKEN) headers['X-Admin-Token'] = ADMIN_TOKEN;
      const res = await fetch('/admin/mark_winner.php', { method:'POST', headers, credentials:'same-origin', body: JSON.stringify(payload) });

      const text = await res.text();
      let json = null;
      try { json = text ? JSON.parse(text) : null; } catch(e) { json = null; }

      if (!res.ok) {
        let msg = `Gagal menyimpan: HTTP ${res.status}`;
        if (json && json.message) msg += ` — ${json.message}`;
        else if (text) msg += ` — ${text}`;
        showSaveError(msg, w);
        return;
      }

      if (!json || !json.success) {
        let msg = 'Gagal menyimpan: ' + (json && json.message ? json.message : (text || 'Unknown response'));
        showSaveError(msg, w);
        return;
      }

      // success: update remaining on card
      const card = document.querySelector('.prize-card.selected');
      if (card) {
        let rem = parseInt(card.dataset.remaining || 0, 10);
        rem = isNaN(rem) ? 0 : Math.max(0, rem - 1);
        card.dataset.remaining = rem;
        const meta = card.querySelector('.meta');
        if (meta) meta.textContent = 'Sisa: ' + rem;
        if (rem <= 0) markPrizeFull(card);
      }

      // show success
      const succ = document.createElement('div'); succ.style.color = '#0f5132'; succ.style.marginTop = '8px';
      succ.textContent = json.message || 'Pemenang disimpan';
      // remove prior error if exists
      const prevErr = winnerBox.querySelector('.msg-error'); if (prevErr) prevErr.remove();
      winnerBox.appendChild(succ);

      // reload segments for selected prize to remove winner from pool
      await loadSegmentsFor(SELECTED_ID);

    } catch (err) {
      console.error('autosave error', err);
      showSaveError('Kesalahan jaringan. Klik Coba Simpan Ulang untuk mencoba kembali.', w);
    } finally {
      const sel = document.querySelector('.prize-card.selected'); const rem = sel ? parseInt(sel.dataset.remaining || 0,10) : 0;
      btnSpin.disabled = !(segments.length>0 && rem>0);
    }
  }

  function showSaveError(msg, winnerMeta) {
    winnerTitle.textContent = winnerMeta.label;
    winnerEmail.textContent = winnerMeta.email || '';
    const prev = winnerBox.querySelector('.msg-error'); if (prev) prev.remove();
    const err = document.createElement('div'); err.className='msg-error'; err.textContent = msg;
    winnerBox.appendChild(err);
    // retry button
    const rb = document.createElement('button'); rb.className = 'btn btn-sm btn-outline-secondary mt-2'; rb.textContent = 'Coba Simpan Ulang';
    rb.onclick = function(){ rb.disabled=true; autosaveWinner(winnerMeta).then(()=> rb.remove()).catch(()=> rb.disabled=false); };
    winnerBox.appendChild(rb);
  }

  // initialize selection
  if (SELECTED_ID) {
    const card = document.querySelector('.prize-card[data-id="'+SELECTED_ID+'"]');
    if (card) card.classList.add('selected');
    if (segments.length === 0) loadSegmentsFor(SELECTED_ID);
  }
  updateSpinState();
})();
</script>

</body>
</html>
