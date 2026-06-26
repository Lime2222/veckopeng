<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
$name    = trim($_POST['name'] ?? '');

if (!$childId || !$name) { $_SESSION['flash_error'] = 'Ange ett namn.'; header("Location: /settings.php?id=$childId"); exit; }
requireChildOwnership($childId, $user['id']);

$stmt = db()->prepare('SELECT MAX(sort_order) FROM requirements WHERE child_id = ?');
$stmt->execute([$childId]);
$max = (int)$stmt->fetchColumn();

db()->prepare('INSERT INTO requirements (child_id, name, sort_order) VALUES (?, ?, ?)')->execute([$childId, $name, $max + 1]);
$_SESSION['flash_success'] = "Krav "$name" tillagt.";
header("Location: /settings.php?id=$childId");
