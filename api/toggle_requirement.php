<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
$reqId   = (int)($_POST['requirement_id'] ?? 0);

if (!$childId || !$reqId) { header("Location: /settings.php?id=$childId"); exit; }
$child = requireChildOwnership($childId, $user['id']);
$familyUserId = (int)$child['user_id'];

$stmt = db()->prepare('SELECT active FROM requirements WHERE id = ? AND user_id = ?');
$stmt->execute([$reqId, $familyUserId]);
$req = $stmt->fetch();
$allowed = ['/family.php'];
$redirect = in_array($_POST['redirect'] ?? '', $allowed) ? $_POST['redirect'] : "/settings.php?id=$childId";
if (!$req) { header("Location: $redirect"); exit; }

db()->prepare('UPDATE requirements SET active = ? WHERE id = ?')->execute([$req['active'] ? 'false' : 'true', $reqId]);
header("Location: $redirect");
