<?php
require_once dirname(__DIR__) . '/src/config.php';
session_start();

$token = $_COOKIE['remember_token'] ?? '';
if ($token) {
    db()->prepare('DELETE FROM remember_tokens WHERE token_hash = ?')
        ->execute([hash('sha256', $token)]);
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

session_destroy();
header('Location: /index.php');
exit;
