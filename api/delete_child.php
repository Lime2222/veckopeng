<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
if (!$childId) { header('Location: /dashboard.php'); exit; }

$child = requireChildOwner($childId, $user['id']);
db()->prepare('DELETE FROM children WHERE id = ?')->execute([$childId]);
$_SESSION['flash_success'] = htmlspecialchars($child['name']) . ' har tagits bort.';
header('Location: /dashboard.php');
