<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// WAJIB load config dulu
$config = require __DIR__ . '/includes/config.php';

// baru load email helper
require __DIR__ . '/includes/email.php';

$result = sendAdminNewRegistration([
    'name'        => 'Test User',
    'email'       => 'testuser@mail.com',
    'phone'       => '08123456789',
    'handicap'    => '10',
    'institution' => 'Test Institution',
]);

echo $result ? 'EMAIL TEST BERHASIL' : 'EMAIL TEST GAGAL';
