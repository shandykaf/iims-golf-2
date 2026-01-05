<?php
/**
 * admin/includes/email.php
 * Email helper menggunakan PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Sesuaikan path autoloader jika perlu, asumsi vendor ada di luar folder admin atau di dalam
// Kita gunakan path relatif yang aman
if (file_exists(__DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';
} else {
    // Fallback jika folder vendor ada di dalam folder admin
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
}

function initMailer(): PHPMailer
{
    global $config;

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $config['smtp']['host'];
    $mail->SMTPAuth   = $config['smtp']['auth'];
    $mail->Username   = $config['smtp']['username'];
    $mail->Password   = $config['smtp']['password'];
    $mail->SMTPSecure = $config['smtp']['encryption'];
    $mail->Port       = $config['smtp']['port'];
    $mail->CharSet    = 'UTF-8';

    // Debugging (matikan di production)
    // $mail->SMTPDebug = 2; 

    $mail->setFrom(
        $config['system_email'],
        $config['system_name']
    );

    return $mail;
}

/* ======================================================
 * EMAIL KE ADMIN – REGISTRASI BARU
 * ====================================================== */
function sendAdminNewRegistration(array $userData): bool
{
    global $config;
    try {
        $mail = initMailer();
        // Support multiple admin emails
        if (!empty($config['admin_email']))  $mail->addAddress($config['admin_email']);
        if (!empty($config['admin_email2'])) $mail->addAddress($config['admin_email2']);

        $mail->isHTML(true);
        $mail->Subject = 'Registrasi Baru - ' . htmlspecialchars($userData['name']);
        $mail->Body = '
            <h3>Registrasi Baru</h3>
            <ul>
                <li><strong>Nama:</strong> ' . htmlspecialchars($userData['name']) . '</li>
                <li><strong>Email:</strong> ' . htmlspecialchars($userData['email']) . '</li>
                <li><strong>Telepon:</strong> ' . htmlspecialchars($userData['phone']) . '</li>
                <li><strong>Handicap:</strong> ' . htmlspecialchars($userData['handicap']) . '</li>
                <li><strong>Instansi:</strong> ' . htmlspecialchars($userData['institution']) . '</li>
                <li><strong>Waktu:</strong> ' . date('d M Y H:i') . '</li>
            </ul>';

        return $mail->send();
    } catch (Throwable $e) {
        error_log('Email admin reg error: ' . $e->getMessage());
        return false;
    }
}

/* ======================================================
 * EMAIL KE USER – APPROVAL & INSTRUKSI PEMBAYARAN
 * ====================================================== */
function sendUserPaymentInstruction(array $user, array $order): bool
{
    global $config;
    try {
        $mail = initMailer();
        $mail->addAddress($user['email'], $user['name']);

        $mail->isHTML(true);
        $mail->Subject = 'Registrasi Disetujui – Instruksi Pembayaran';

        $paymentUrl = rtrim($config['app']['base_url'], '/') . '/payment.php?order=' . urlencode($order['order_code']);

        $mail->Body = '
            <div style="background:#f4f6f8;padding:30px 0;font-family:Arial,Helvetica,sans-serif;">
                <div style="max-width:600px;margin:0 auto;background:#ffffff;padding:30px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.05);">
        
                    <p>Yth. Bapak/Ibu <strong>' . htmlspecialchars($user['name']) . '</strong>,</p>
        
                    <p>
                        Terima kasih telah melakukan pendaftaran sebagai peserta 
                        <strong>IIMS Golf Tournament 2026</strong>. Kami mengapresiasi antusiasme serta partisipasi
                        Bapak/Ibu dalam mengikuti turnamen ini.
                    </p>
        
                    <p>
                        Sebagai tindak lanjut proses registrasi, kami informasikan bahwa biaya pendaftaran sebesar 
                        <strong>Rp1.800.000</strong> mohon dapat segera dilakukan pembayaran paling lambat 
                        <strong>24 jam</strong> sejak email ini diterima melalui rekening berikut:
                    </p>
        
                    <div style="background:#f7f7f7;padding:15px;border-radius:6px;margin:15px 0;">
                        <strong>Bank Danamon</strong><br>
                        No. Rekening: <strong>007710077772</strong><br>
                        a.n. <strong>Dyandra Promosindo</strong>
                    </div>
        
                    <p>
                        Apabila hingga batas waktu tersebut kami belum menerima konfirmasi pembayaran, maka pendaftaran
                        akan kami anggap <strong>batal</strong> atau peserta <strong>mengundurkan diri</strong> dari
                        IIMS Golf Tournament 2026.
                    </p>
        
                    <p>
                        Setelah melakukan pembayaran, mohon kesediaan Bapak/Ibu untuk mengirimkan atau mengunggah bukti
                        transfer sebagai bagian dari proses verifikasi keikutsertaan.
                    </p>
        
                    <p style="text-align:center;margin:30px 0;">
                        <a href="' . $paymentUrl . '" style="background:#d9534f; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;">
                            UPLOAD BUKTI PEMBAYARAN
                        </a>
                    </p>
        
                    <p style="font-size:13px;color:#555;">
                        Alternatifnya, bukti pembayaran juga dapat dikirimkan dengan membalas email ini secara langsung.
                    </p>
        
                    <hr style="border:none;border-top:1px solid #e0e0e0;margin:25px 0;">
        
                    <p style="font-size:13px;color:#555;">
                        Apabila Bapak/Ibu membutuhkan informasi lebih lanjut, silakan menghubungi kami melalui:<br>
                        Email: <a href="mailto:contact@iimsgolftournament.com">contact@iimsgolftournament.com</a><br>
                        WhatsApp: 087890764125<br>
                        Instagram: @iimsgolftournament
                    </p>
        
                    <p style="margin-top:25px;">
                        Hormat kami,<br>
                        <strong>Panitia IIMS Golf Tournament 2026</strong><br>
                        Dyandra Promosindo
                    </p>
        
                </div>
            </div>
        ';

        return $mail->send();
    } catch (Throwable $e) {
        error_log('Email user payment error: ' . $e->getMessage());
        return false;
    }
}

/* ======================================================
 * EMAIL KE ADMIN – NOTIFIKASI BUKTI BAYAR BARU (NEW)
 * ====================================================== */
function sendAdminPaymentNotification(array $orderData, string $fileUrl): bool
{
    global $config;
    try {
        $mail = initMailer();
        if (!empty($config['admin_email']))  $mail->addAddress($config['admin_email']);
        if (!empty($config['admin_email2'])) $mail->addAddress($config['admin_email2']);

        $mail->isHTML(true);
        $mail->Subject = 'Bukti Bayar Baru - ' . htmlspecialchars($orderData['name']);

        $mail->Body = '
            <h3>Konfirmasi Pembayaran Baru</h3>
            <p>Peserta telah mengunggah bukti pembayaran.</p>
            <ul>
                <li><strong>Nama:</strong> ' . htmlspecialchars($orderData['name']) . '</li>
                <li><strong>Order Code:</strong> ' . htmlspecialchars($orderData['order_code']) . '</li>
                <li><strong>Waktu Upload:</strong> ' . date('d M Y H:i') . '</li>
            </ul>
            <p>
                <strong>Lihat Bukti:</strong><br>
                <a href="' . $fileUrl . '">' . $fileUrl . '</a>
            </p>
            <p>Silakan login ke Admin Dashboard untuk memverifikasi.</p>
        ';

        return $mail->send();
    } catch (Throwable $e) {
        error_log('Email admin payment error: ' . $e->getMessage());
        return false;
    }
}