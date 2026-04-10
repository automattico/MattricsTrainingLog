<?php
/**
 * Shared HTML shell for login.php and register.php.
 * Callers must define $authPageTitle and $authPageBody before including this file.
 *
 * @var string $authPageTitle  <title> text
 * @var string $authPageBody   HTML to inject inside .auth-card
 * @var string $authPageClass  Optional body class
 */
$authPageClass = isset($authPageClass) ? (string) $authPageClass : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($authPageTitle, ENT_QUOTES, 'UTF-8') ?> — Mattrics</title>
<link rel="icon" type="image/svg+xml" href="icons/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32x32.png">
<link rel="apple-touch-icon" sizes="180x180" href="icons/apple-touch-icon.png">
<meta name="theme-color" content="#09121d">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/brand.css">
<style>
  *, *::before, *::after { box-sizing: border-box; }

  body {
    margin: 0;
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-bg, #09121d);
    font-family: "DM Sans", sans-serif;
    color: var(--color-text, #e8edf2);
  }

  .auth-wrap {
    width: 100%;
    max-width: 400px;
    padding: 2rem 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2rem;
  }

  .auth-logo {
    text-align: center;
  }

  .auth-logo-brand {
    font-family: "Barlow Condensed", sans-serif;
    font-weight: 800;
    font-size: clamp(5rem, 18vw, 7.2rem);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--brand, #0082c9);
    line-height: 0.82;
  }

  .auth-logo-sub {
    font-family: "DM Sans", sans-serif;
    font-weight: 300;
    font-size: clamp(1.7rem, 4.8vw, 2.4rem);
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.78);
    margin-top: 0.55rem;
  }

  .auth-page-login .auth-wrap {
    max-width: 520px;
    gap: 2.5rem;
  }

  .auth-card {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 20px;
    padding: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
    text-align: center;
  }

  .auth-heading {
    font-family: "Barlow Condensed", sans-serif;
    font-weight: 700;
    font-size: 1.4rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #fff;
    margin: 0;
  }

  .auth-desc {
    font-size: 0.9rem;
    font-weight: 300;
    color: rgba(255,255,255,0.6);
    line-height: 1.5;
    margin: 0;
  }

  .auth-btn {
    width: 100%;
    padding: 0.85rem 1.5rem;
    background: var(--color-accent, #4f8ef7);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-family: "DM Sans", sans-serif;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
  }

  .auth-btn:hover:not(:disabled) { opacity: 0.88; }
  .auth-btn:active:not(:disabled) { transform: scale(0.98); }
  .auth-btn:disabled { opacity: 0.45; cursor: not-allowed; }

  .auth-error {
    display: none;
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    background: rgba(255, 141, 112, 0.12);
    border: 1px solid rgba(255, 141, 112, 0.3);
    color: #ff8d70;
    font-size: 0.875rem;
    line-height: 1.4;
    text-align: left;
  }

  .auth-error.visible { display: block; }

  .auth-note {
    font-size: 0.8rem;
    font-weight: 300;
    color: rgba(255,255,255,0.35);
    line-height: 1.5;
    margin: 0;
  }

  .auth-note code {
    font-family: "DM Mono", monospace;
    font-size: 0.78rem;
    background: rgba(255,255,255,0.07);
    padding: 0.1em 0.35em;
    border-radius: 4px;
  }
</style>
</head>
<body class="<?= htmlspecialchars($authPageClass, ENT_QUOTES, 'UTF-8') ?>">
<div class="auth-wrap">
  <div class="auth-logo">
    <div class="auth-logo-brand">Mattrics</div>
    <div class="auth-logo-sub">Training Log</div>
  </div>
  <div class="auth-card">
    <?= $authPageBody ?>
  </div>
</div>
</body>
</html>
