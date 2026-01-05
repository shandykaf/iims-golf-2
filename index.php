<?php
// index.php
$config = require __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>IIMS Golf Turnament</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Spline+Sans:wght@300;400;600&display=swap" rel="stylesheet">

  <style>
    :root {
      --font-heading: "Inter";
      --font-body: "Spline Sans";

      --color-white: #ffffff;
      --color-black: #1e1e1e;
      --color-text-muted: #666;
      --color-danger: #d82323;
      --color-accent: #f43232;

      --input-border: #CECECE;
      --input-radius: 10px;
      --input-bg: #ffffff;

      --typo-heading-sm-desktop: 30px;
      --typo-heading-sm-mobile: 24px;
      --typo-weight-semibold: 600;

      --typo-body-md-desktop: 16px;
      --typo-body-md-mobile: 15px;
      --typo-weight-regular: 400;

      --typo-small: 12px;

      --form-max-width: 720px;
    }

    body {
      margin: 0;
    }

    .register-header {
      display: flex;
      flex-direction: row;
      justify-content: space-between;
      align-items: center;
      text-align: center;
      background: #F5F5F5;
      padding: 20px;
    }

    .reg-logo {
      height: 60px;
    }

    .btn-submit {
      padding: 15px 35px;
      border-radius: 100px;
      border: none;
      background: var(--color-accent);
      color: #fff;
      font-family: var(--font-heading);
      font-weight: 700;
      font-size: 16px;
      text-transform: uppercase;
      height: fit-content;
      text-decoration: unset;
    }
  </style>
</head>

<body>

  <header class="register-header">
    <img src="assets/img/img-logo-black.png" class="reg-logo" alt="<?=htmlspecialchars($siteTitle)?>">
    <a href="/register.php" class="btn-submit">DAFTAR SEKARANG</a>
  </header>

  <div class="wrapper banner">
    <img width="100%" src="../assets/img/img-banner.jpg" alt="">
  </div>
</body>
</html>