<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId   = (int)($_POST['child_id'] ?? 0);
$reqId     = (int)($_POST['requirement_id'] ?? 0);
$frequency = $_POST['frequency'] ?? 'daily';

if (!in_array($frequency, ['daily', 'weekly'])) $frequency = 'daily';
if (!$childId || !$reqId) { header("Location: /settings.php?id=$childId"); exit; }
$child = requireChildOwnership($childId, $user['id']);
$familyUserId = (int)$child['user_id'];

db()->prepare('UPDATE requirements SET frequency = ? WHERE id = ? AND user_id = ?')
    ->execute([$frequency, $reqId, $familyUserId]);

header("Location: /settings.php?id=$childId");
