<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
if (!$childId) { header('Location: /dashboard.php'); exit; }
requireChildOwnership($childId, $user['id']);

db()->prepare('DELETE FROM invitations WHERE child_id = ? AND role = ? AND expires_at < NOW()')->execute([$childId, 'child']);

$token = bin2hex(random_bytes(32));
db()->prepare('
    INSERT INTO invitations (child_id, invited_by, token, role)
    VALUES (?, ?, ?, \'child\')
')->execute([$childId, $user['id'], $token]);

$_SESSION['flash_child_invite_token'] = $token;
header("Location: /settings.php?id=$childId");
