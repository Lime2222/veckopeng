<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId      = (int)($_POST['child_id'] ?? 0);
$name         = trim($_POST['name'] ?? '');
$weeklyAmount = (float)($_POST['weekly_amount'] ?? 0);
$color        = $_POST['avatar_color'] ?? '#6366f1';
$swishNumber  = preg_replace('/[^0-9+]/', '', trim($_POST['swish_number'] ?? '')) ?: null;

if (!$childId || !$name) { $_SESSION['flash_error'] = 'Ogiltiga uppgifter.'; header("Location: /settings.php?id=$childId"); exit; }
if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) $color = '#6366f1';
requireChildOwnership($childId, $user['id']);

db()->prepare('UPDATE children SET name = ?, weekly_amount = ?, avatar_color = ?, swish_number = ? WHERE id = ?')
    ->execute([$name, max(0, $weeklyAmount), $color, $swishNumber, $childId]);

$_SESSION['flash_success'] = 'Inställningar sparade.';
header("Location: /settings.php?id=$childId");
