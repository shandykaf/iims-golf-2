<?php
// index.php
$config = require __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Situs Dalam Pengembangan</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #f9fafb;
      color: #1f2937;
    }

    .wrapper {
      text-align: center;
      padding: 48px 32px;
      max-width: 450px;
      width: 100%;
    }

    /* Icon Container */
    .icon-box {
      width: 78px;
      height: 78px;
      margin: 0 auto 26px;
      border-radius: 16px;
      background: #e5e7eb;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .icon-box svg {
      width: 38px;
      height: 38px;
      stroke: #4b5563;
    }

    .title {
      font-size: 26px;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .desc {
      font-size: 15px;
      color: #6b7280;
      line-height: 1.6;
    }

    .footer-line {
      margin-top: 40px;
      width: 100%;
      height: 1px;
      background: #e5e7eb;
    }

    .note {
      font-size: 13px;
      color: #9ca3af;
      margin-top: 18px;
    }
  </style>
</head>
<body>
  <div class="wrapper">

    <!-- Icon menggantikan logo -->
    <div class="icon-box">
      <!-- Icon "Tools / Settings" -->
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke-width="1.8" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.894 3.31.873 2.417 2.417a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.894 1.543-.873 3.31-2.417 2.417a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.894-3.31-.873-2.417-2.417a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.894-1.543.873-3.31 2.417-2.417 1.04.602 2.364.013 2.573-1.066z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
    </div>

    <h1 class="title">Situs Sedang Dalam Pengembangan</h1>

    <p class="desc">
      Kami sedang menyiapkan tampilan terbaik untuk Anda.  
      Halaman ini akan segera dapat diakses dalam waktu dekat.
    </p>

    <div class="footer-line"></div>
    <p class="note">Coming soon</p>

  </div>
</body>
</html>