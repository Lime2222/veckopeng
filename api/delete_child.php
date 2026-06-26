<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
verifyCsrf();

$childId = (int)($_POST['child_id'] ?? 0);
if (!$childId) { header('Location: /dashboard.php'); exit; }

$child = requireChildOwnership($childId, $user['id']);
db()->prepare('DELETE FROM children WHERE id = ?')->execute([$childId]);
$_SESSION['flash_success'] = htmlspecialchars($child['name']) . ' har tagits bort.';
header('Location: /dashboard.php');
