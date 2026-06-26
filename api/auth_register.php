<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

startSession();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /index.php'); exit; }

if (!verifyCsrf()) {
    $_SESSION['auth_error'] = 'Sessionsfel. Försök igen.';
    header('Location: /index.php'); exit;
}

$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$name || !$email || !$password) {
    $_SESSION['auth_error'] = 'Fyll i alla fält.';
    header('Location: /index.php'); exit;
}
if (strlen($password) < 8) {
    $_SESSION['auth_error'] = 'Lösenordet måste vara minst 8 tecken.';
    header('Location: /index.php'); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['auth_error'] = 'Ogiltig e-postadress.';
    header('Location: /index.php'); exit;
}

$stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    $_SESSION['auth_error'] = 'E-postadressen används redan.';
    header('Location: /index.php'); exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = db()->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?) RETURNING id');
$stmt->execute([$email, $hash, $name]);
$userId = $stmt->fetchColumn();

session_regenerate_id(true);
$_SESSION['user_id']   = $userId;
$_SESSION['user_name'] = $name;
$_SESSION['flash_success'] = 'Välkommen, ' . $name . '! Ditt konto är skapat.';

if (!empty($_SESSION['pending_invite'])) {
    header('Location: /accept_invite.php');
} else {
    header('Location: /dashboard.php');
}
