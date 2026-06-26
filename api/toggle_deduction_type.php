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

$stmt = db()->prepare('SELECT active FROM deduction_types WHERE id = ? AND user_id = ?');
$stmt->execute([$dtId, $familyUserId]);
$dt = $stmt->fetch();
if (!$dt) { header("Location: /settings.php?id=$childId"); exit; }

db()->prepare('UPDATE deduction_types SET active = ? WHERE id = ?')->execute([$dt['active'] ? 'false' : 'true', $dtId]);
$allowed = ['/family.php'];
$redirect = in_array($_POST['redirect'] ?? '', $allowed) ? $_POST['redirect'] : "/settings.php?id=$childId";
header("Location: $redirect");
