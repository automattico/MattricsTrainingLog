<?php
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap-auth.php';
mattrics_auth_session_start();
if (mattrics_auth_requires_https() && !mattrics_is_https_request()) {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
}
if (!empty($_SESSION['mattrics_authed']) && mattrics_session_is_timed_out()) {
    mattrics_audit_log('session_timeout', ['outcome' => 'success']);
    mattrics_clear_auth_session();
}
if (empty($_SESSION['mattrics_authed'])) {
    header('Location: /login.php');
    exit;
}
mattrics_touch_auth_session();
$_csrfToken = mattrics_csrf_token();
$_assetVersion = (string) (@filemtime(__DIR__ . '/assets/js/passkeys.js') ?: time());
$_initialView = '';
if (isset($_GET['view']) && preg_match('/^[a-z]+$/', $_GET['view'])) {
    $_initialView = $_GET['view'];
}
?><!DOCTYPE html>
<html lang="en">
<?php require_once __DIR__ . '/views/head.php'; ?>
<body>

<?php require_once __DIR__ . '/views/load-screen.php'; ?>

<div id="app">
  <div class="app-shell">
    <?php require_once __DIR__ . '/views/header.php'; ?>
    <?php require_once __DIR__ . '/views/nav.php'; ?>
    <main class="app-main">
      <?php require_once __DIR__ . '/views/main-views.php'; ?>
    </main>
  </div>
</div>

<?php require_once __DIR__ . '/views/detail-modal.php'; ?>
<?php require_once __DIR__ . '/views/scripts.php'; ?>
</body>
</html>
