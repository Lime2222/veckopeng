<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId   = (int)($_POST['child_id'] ?? 0);
$name      = trim($_POST['name'] ?? '');
$type      = $_POST['type'] ?? 'checkbox';
$targetMin = (int)($_POST['weekly_target_minutes'] ?? 0);

if (!in_array($type, ['checkbox', 'minutes'])) $type = 'checkbox';
if (!$childId || !$name) { $_SESSION['flash_error'] = 'Ange ett namn.'; header("Location: /settings.php?id=$childId"); exit; }
if ($type === 'minutes' && $targetMin <= 0) { $_SESSION['flash_error'] = 'Ange ett veckamål i minuter.'; header("Location: /settings.php?id=$childId"); exit; }
requireChildOwnership($childId, $user['id']);

$stmt = db()->prepare('SELECT MAX(sort_order) FROM requirements WHERE child_id = ?');
$stmt->execute([$childId]);
$max = (int)$stmt->fetchColumn();

$freq = $type === 'minutes' ? 'weekly' : 'daily';
db()->prepare('INSERT INTO requirements (child_id, name, sort_order, type, frequency, weekly_target_minutes) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$childId, $name, $max + 1, $type, $freq, $type === 'minutes' ? $targetMin : null]);
$_SESSION['flash_success'] = 'Krav ' . $name . ' tillagt.';
header("Location: /settings.php?id=$childId");
