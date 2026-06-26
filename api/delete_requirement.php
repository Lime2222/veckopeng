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

db()->prepare('DELETE FROM requirements WHERE id = ? AND user_id = ?')->execute([$reqId, $familyUserId]);
$_SESSION['flash_success'] = 'Krav borttaget.';
header("Location: /settings.php?id=$childId");
