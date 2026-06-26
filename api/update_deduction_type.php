<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
$dtId    = (int)($_POST['deduction_type_id'] ?? 0);
$name    = trim($_POST['name'] ?? '');
$amount  = (float)($_POST['amount'] ?? 0);

if (!$childId || !$dtId || !$name || $amount == 0) { $_SESSION['flash_error'] = 'Ogiltiga uppgifter.'; header("Location: /settings.php?id=$childId"); exit; }
$child = requireChildOwnership($childId, $user['id']);
$familyUserId = (int)$child['user_id'];

db()->prepare('UPDATE deduction_types SET name = ?, amount = ? WHERE id = ? AND user_id = ?')->execute([$name, $amount, $dtId, $familyUserId]);
$_SESSION['flash_success'] = 'Avdrag/bonus uppdaterat.';
header("Location: /settings.php?id=$childId");
