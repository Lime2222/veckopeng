<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
verifyCsrf();

$childId = (int)($_POST['child_id'] ?? 0);
$reqId   = (int)($_POST['requirement_id'] ?? 0);

if (!$childId || !$reqId) { header("Location: /settings.php?id=$childId"); exit; }
requireChildOwnership($childId, $user['id']);

$stmt = db()->prepare('SELECT active FROM requirements WHERE id = ? AND child_id = ?');
$stmt->execute([$reqId, $childId]);
$req = $stmt->fetch();
if (!$req) { header("Location: /settings.php?id=$childId"); exit; }

db()->prepare('UPDATE requirements SET active = ? WHERE id = ?')->execute([$req['active'] ? 'false' : 'true', $reqId]);
header("Location: /settings.php?id=$childId");
