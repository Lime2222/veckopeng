<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

startSession();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /index.php'); exit; }

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!verifyCsrf()) {
    $_SESSION['auth_error'] = 'Sessionsfel. Försök igen.';
    header('Location: /index.php'); exit;
}

if (!$email || !$password) {
    $_SESSION['auth_error'] = 'Fyll i e-post och lösenord.';
    header('Location: /index.php'); exit;
}

$stmt = db()->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['auth_error'] = 'Fel e-post eller lösenord.';
    header('Location: /index.php'); exit;
}

session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['name'];

if (!empty($_POST['remember_me'])) {
    $token  = bin2hex(random_bytes(32));
    $hash   = hash('sha256', $token);
    db()->prepare('INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL \'30 days\')')
        ->execute([$user['id'], $hash]);
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('remember_token', $token, time() + 30 * 86400, '/', '', $secure, true);
}

if (!empty($_SESSION['pending_invite'])) {
    header('Location: /accept_invite.php');
} elseif (!empty($_SESSION['redirect_after_login'])) {
    $redirect = $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
} else {
    header('Location: /dashboard.php');
}
