<?php
// admin/mark_winner.php
// Final version: validates -> saves winner to dp_winners -> sends email via PHPMailer (SMTP from includes/config.php)
// Place this file in /admin/mark_winner.php

header('Content-Type: application/json; charset=utf-8');

function respond($data, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function log_admin($msg) {
    @file_put_contents(sys_get_temp_dir() . '/admin_mark_winner.log', "[".date('c')."] " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

session_start();

// load app config
$configPath = __DIR__ . '/../includes/config.php';
if (!file_exists($configPath)) {
    log_admin("config.php not found at expected path: {$configPath}");
    respond(['success'=>false,'message'=>'Server misconfiguration (missing config).'],500);
}
$config = require $configPath;

// require DB (includes/db.php should create $pdo)
require __DIR__ . '/../includes/db.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    log_admin("PDO not initialized in includes/db.php");
    respond(['success'=>false,'message'=>'Server DB misconfiguration.'],500);
}

// AUTH: session OR X-Admin-Token
$authorized = false;
if (!empty($_SESSION['admin_logged_in'])) {
    $authorized = true;
} else {
    if (function_exists('getallheaders')) {
        $h = array_change_key_case(getallheaders(), CASE_LOWER);
        $token = $h['x-admin-token'] ?? '';
        if ($token) {
            try {
                $stm = $pdo->prepare("SELECT id FROM admins WHERE api_token = ? LIMIT 1");
                $stm->execute([$token]);
                if ($stm->fetch()) $authorized = true;
            } catch (Exception $e) {
                log_admin("Auth token lookup error: ".$e->getMessage());
            }
        }
    }
}
if (!$authorized) respond(['success'=>false,'message'=>'Unauthorized. Admin login required.'],401);

// only accept POST JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Use POST.'],405);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) respond(['success'=>false,'message'=>'Malformed JSON body.'],400);

$doorprize_id = isset($data['doorprize_id']) ? (int)$data['doorprize_id'] : 0;
$user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$name = isset($data['name']) ? trim($data['name']) : null;
$email = isset($data['email']) ? filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL) : null;
$prize = isset($data['prize']) ? trim($data['prize']) : null;

if ($doorprize_id <= 0 || $user_id <= 0) respond(['success'=>false,'message'=>'Missing or invalid doorprize_id / user_id.'],400);

try {
    // validate doorprize
    $stmt = $pdo->prepare("SELECT id,title,qty FROM doorprizes WHERE id = ? LIMIT 1");
    $stmt->execute([$doorprize_id]);
    $dp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dp) respond(['success'=>false,'message'=>'Doorprize not found.'],404);
    $dpTitle = $dp['title'] ?? '';

    // validate user
    $stmt = $pdo->prepare("SELECT id,name,email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) respond(['success'=>false,'message'=>'User not found.'],404);
    if (empty($name)) $name = $u['name'] ?? null;
    if (empty($email)) $email = $u['email'] ?? null;

    // eligibility: at least one paid order
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'paid'");
    $stmt->execute([$user_id]);
    if ((int)$stmt->fetchColumn() <= 0) respond(['success'=>false,'message'=>'User has no paid order (not eligible).'],400);

    // transaction: lock and check
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT qty FROM doorprizes WHERE id = ? FOR UPDATE");
    $stmt->execute([$doorprize_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $pdo->rollBack(); respond(['success'=>false,'message'=>'Doorprize not found (lock).'],404); }
    $qty = (int)$row['qty'];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dp_winners WHERE doorprize_id = ?");
    $stmt->execute([$doorprize_id]);
    $currentWinners = (int)$stmt->fetchColumn();

    if ($qty > 0 && $currentWinners >= $qty) {
        $pdo->rollBack();
        respond(['success'=>false,'message'=>'Kuota pemenang sudah terpenuhi untuk hadiah ini.'],409);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dp_winners WHERE doorprize_id = ? AND user_id = ?");
    $stmt->execute([$doorprize_id, $user_id]);
    if ((int)$stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        respond(['success'=>false,'message'=>'User sudah tercatat sebagai pemenang untuk doorprize ini.'],409);
    }

    // dynamic insert into dp_winners
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM dp_winners")->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Exception $e) {
        $pdo->rollBack();
        log_admin("SHOW COLUMNS dp_winners failed: ".$e->getMessage());
        respond(['success'=>false,'message'=>'Server error (dp_winners).'],500);
    }

    $map = [
        'doorprize_id' => $doorprize_id,
        'user_id' => $user_id,
        'winner_name' => $name,
        'winner_email' => $email,
        'prize' => ($prize ?? $dpTitle),
        'prize_given' => 0,
        'given_at' => null,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $insertCols = $placeholders = $insertValues = [];
    foreach ($map as $col => $val) {
        if (in_array($col, $cols, true)) {
            $insertCols[] = $col;
            $placeholders[] = '?';
            $insertValues[] = $val;
        }
    }

    if (count($insertCols) === 0) {
        $pdo->rollBack();
        respond(['success'=>false,'message'=>'Table dp_winners has no writable columns.'],500);
    }

    $insertSql = "INSERT INTO dp_winners (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $placeholders) . ")";
    try {
        $stmtIns = $pdo->prepare($insertSql);
        $stmtIns->execute($insertValues);
    } catch (Exception $e) {
        $pdo->rollBack();
        $logMsg = "Insert dp_winners failed: " . $e->getMessage() . "\nSQL: " . $insertSql . "\nValues: " . json_encode($insertValues) . "\nTrace:\n" . $e->getTraceAsString();
        log_admin($logMsg);
        respond(['success'=>false,'message'=>'Server error while saving winner.','detail'=>$e->getMessage()],500);
    }

    $pdo->commit();

    // --------------------------
    // Send email using PHPMailer
    // --------------------------
    $email_sent = false;
    $email_error = null;

    // detect PHPMailer: try composer autoload, then vendor paths
    $foundPhpmailer = false;
    $autoloadCandidates = [
        __DIR__ . '/../vendor/autoload.php', // admin/../vendor/autoload.php
        $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php',
        $_SERVER['DOCUMENT_ROOT'] . '/client/iimsgolftournament.com/vendor/autoload.php'
    ];
    foreach ($autoloadCandidates as $a) {
        if (file_exists($a)) {
            require_once $a;
            $foundPhpmailer = true;
            break;
        }
    }

    if (!$foundPhpmailer) {
        // try direct src include paths
        $srcCandidates = [
            $_SERVER['DOCUMENT_ROOT'] . '/vendor/phpmailer/phpmailer/src/',
            $_SERVER['DOCUMENT_ROOT'] . '/client/iimsgolftournament.com/vendor/phpmailer/phpmailer/src/',
            __DIR__ . '/../vendor/phpmailer/phpmailer/src/'
        ];
        foreach ($srcCandidates as $base) {
            $f1 = rtrim($base, '/') . '/PHPMailer.php';
            $f2 = rtrim($base, '/') . '/SMTP.php';
            $f3 = rtrim($base, '/') . '/Exception.php';
            if (file_exists($f1) && file_exists($f2) && file_exists($f3)) {
                require_once $f1;
                require_once $f2;
                require_once $f3;
                $foundPhpmailer = true;
                break;
            }
        }
    }

    if (!$foundPhpmailer) {
        log_admin("PHPMailer not found; tried autoloads and vendor src paths");
        // return success for DB save but indicate email not sent
        respond(['success'=>true,'message'=>'Pemenang disimpan, email not sent (PHPMailer missing).','email_sent'=>false,'email_error'=>'PHPMailer not found'],200);
    }

    // PHPMailer is available — use SMTP config from includes/config.php
    $smtpCfg = $config->smtp ?? null;
    if (!$smtpCfg) {
        log_admin("SMTP config missing in config.php");
        respond(['success'=>true,'message'=>'Pemenang disimpan, email not sent (SMTP config missing).','email_sent'=>false,'email_error'=>'SMTP config missing'],200);
    }

    // Use PHPMailer namespace (if composer autoload) or direct class names
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpCfg->host ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtpCfg->username ?? '';
        $mail->Password = $smtpCfg->password ?? '';
        $secure = strtolower($smtpCfg->secure ?? 'tls');
        if ($secure === 'ssl') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        else $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)($smtpCfg->port ?? 587);

        $fromEmail = $smtpCfg->from_email ?? ($smtpCfg->username ?? 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromName  = $smtpCfg->from_name ?? ($config->site_name ?? 'No Reply');

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name);
        $mail->isHTML(false);
        $mail->Subject = "Selamat — Anda terpilih sebagai pemenang: " . ($prize ?? $dpTitle);
        $body  = "Halo " . ($name ?? '') . ",\n\n";
        $body .= "Selamat! Anda telah dipilih sebagai pemenang hadiah: " . ($prize ?? $dpTitle) . ".\n";
        $body .= "Tim kami akan menghubungi Anda untuk proses pengambilan hadiah.\n\n";
        $body .= "Terima kasih.\n";
        $mail->Body = $body;

        $email_sent = $mail->send();
        if (!$email_sent) {
            $email_error = $mail->ErrorInfo ?? 'Unknown error sending email';
            log_admin("PHPMailer send returned false. ErrorInfo: " . $email_error);
        }
    } catch (\PHPMailer\PHPMailer\Exception $ex) {
        $email_sent = false;
        $email_error = $ex->getMessage();
        log_admin("PHPMailer Exception: " . $email_error . "\nTrace:\n" . $ex->getTraceAsString());
    } catch (Exception $ex2) {
        $email_sent = false;
        $email_error = $ex2->getMessage();
        log_admin("General Exception while sending email: " . $email_error . "\nTrace:\n" . $ex2->getTraceAsString());
    }

    // done
    respond([
        'success' => true,
        'message' => 'Pemenang berhasil disimpan.',
        'email_sent' => (bool)$email_sent,
        'email_error' => $email_error ?: null
    ], 200);

} catch (Exception $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_admin("Unhandled exception mark_winner: " . $ex->getMessage() . "\nTrace:\n" . $ex->getTraceAsString());
    respond(['success'=>false,'message'=>'Unhandled server error.','detail'=>$ex->getMessage()],500);
}
