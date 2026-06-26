<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
if (!$childId) { header("Location: /settings.php?id=$childId"); exit; }
requireChildOwnership($childId, $user['id']);

$stmt = db()->prepare('SELECT child_can_self_report FROM children WHERE id = ?');
$stmt->execute([$childId]);
$current = (bool)$stmt->fetchColumn();

db()->prepare('UPDATE children SET child_can_self_report = ? WHERE id = ?')
    ->execute([$current ? 'false' : 'true', $childId]);

header("Location: /settings.php?id=$childId");
