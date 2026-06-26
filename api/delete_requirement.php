<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
$reqId   = (int)($_POST['requirement_id'] ?? 0);

if (!$childId || !$reqId) { header("Location: /settings.php?id=$childId"); exit; }
requireChildOwnership($childId, $user['id']);

db()->prepare('DELETE FROM requirements WHERE id = ? AND child_id = ?')->execute([$reqId, $childId]);
$_SESSION['flash_success'] = 'Krav borttaget.';
header("Location: /settings.php?id=$childId");
