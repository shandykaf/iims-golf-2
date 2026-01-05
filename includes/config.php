<?php
/**
 * Global configuration file
 * Semua file wajib include config ini
 */

return [

    /* ===============================
     * DATABASE CONFIG
     * =============================== */
    'db' => [
        'host' => 'localhost',
        'name' => 'solusib1_staging-iims',
        'user' => 'solusib1_staging',
        'pass' => 'sbd2025**',
        'charset' => 'utf8mb4',
    ],

    /* ===============================
    * SYSTEM EMAIL (SENDER)
    * =============================== */
    'system_email' => 'noreply@iimsgolftournament.com',
    'system_name'  => 'STAGING - IIMS Golf Tournament',

    /* ===============================
    * ADMIN EMAIL (RECIPIENT)
    * =============================== */
    // 'admin_email' => 'gevio.dyandra@gmail.com',
    // 'admin_email2' => 'samuel.david@dyandra.com ',
    'admin_email' => 'production.solusibisnisdigital@gmail.com',
    'admin_email2' => 'brian.solusibisnisdigital@gmail.com',

    /* ===============================
    * SMTP CONFIG (EMAIL HOSTING)
    * =============================== */
    'smtp' => [
        'host'       => 'mail.iimsgolftournament.com',
        'username'   => 'noreply@iimsgolftournament.com',
        'password'   => 'ryRD4C#UbX32?HVj',
        'port'       => 465,
        'encryption' => 'ssl',
        'auth'       => true,
        'debug'      => false, // sementara untuk test
    ],

    /* ===============================
     * APP CONFIG
     * =============================== */
    'app' => [
        'base_url' => 'http://iims.sbd-dev.my.id',
        'timezone' => 'Asia/Jakarta',
    ],

];
