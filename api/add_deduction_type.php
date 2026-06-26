<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
$name    = trim($_POST['name'] ?? '');
$amount  = (float)($_POST['amount'] ?? 0);

if (!$childId || !$name || $amount == 0) { $_SESSION['flash_error'] = 'Fyll i alla fält.'; header("Location: /settings.php?id=$childId"); exit; }
requireChildOwnership($childId, $user['id']);

db()->prepare('INSERT INTO deduction_types (child_id, name, amount) VALUES (?, ?, ?)')->execute([$childId, $name, $amount]);
$_SESSION['flash_success'] = "Lagt till: "$name" (" . ($amount > 0 ? '+' : '') . number_format($amount, 2, ',', ' ') . ' kr).';
header("Location: /settings.php?id=$childId");
