<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
$dtId    = (int)($_POST['deduction_type_id'] ?? 0);

if (!$childId || !$dtId) { header("Location: /settings.php?id=$childId"); exit; }
$child = requireChildOwnership($childId, $user['id']);
$familyUserId = (int)$child['user_id'];

db()->prepare('DELETE FROM deduction_types WHERE id = ? AND user_id = ?')->execute([$dtId, $familyUserId]);
$_SESSION['flash_success'] = 'Avdrag/bonus borttaget.';
header("Location: /settings.php?id=$childId");
