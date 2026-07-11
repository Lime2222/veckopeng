<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    $_SESSION['flash_error'] = 'Skriv ditt förslag innan du skickar.';
    header('Location: /dashboard.php'); exit;
}

db()->prepare('INSERT INTO suggestions (user_id, message) VALUES (?, ?)')
    ->execute([$user['id'], mb_substr($message, 0, 5000)]);

$_SESSION['flash_success'] = 'Tack för ditt förslag! 💡';
header('Location: /dashboard.php');
