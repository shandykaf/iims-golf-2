    <?php
// scripts/email_test_phpmailer.php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/email.php';

$testTo = 'production.solusibisnisdigital@gmail.com'; // <-- GANTI dengan emailmu
$subject = 'PHPMailer TEST - iimsgolftournament';
$html = "<p>Test email via PHPMailer. Time: " . date('Y-m-d H:i:s') . "</p>";

$result = sendEmail($testTo, $subject, $html);

echo "sendEmail returned: " . ($result ? 'true' : 'false') . "<br>\n";
echo "Check /tmp/email_debug.log for details.<br><br>\n";

$log = @file_get_contents('/tmp/email_debug.log');
if ($log === false) {
    echo "No /tmp/email_debug.log found or not readable.<br>";
} else {
    // show last 200 lines
    $lines = explode("\n", trim($log));
    $tail = array_slice($lines, -200);
    echo "<pre>" . htmlspecialchars(implode("\n", $tail)) . "</pre>";
}
