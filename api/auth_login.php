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
header('Location: /dashboard.php');
