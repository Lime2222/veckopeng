<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
$name    = trim($_POST['name'] ?? '');
$amount  = (float)($_POST['amount'] ?? 0);
$unit    = ($_POST['unit'] ?? 'kr') === 'min' ? 'min' : 'kr';

if (!$childId || !$name || $amount == 0) { $_SESSION['flash_error'] = 'Fyll i alla fält.'; header("Location: /settings.php?id=$childId"); exit; }
$child = requireChildOwnership($childId, $user['id']);
$familyUserId = (int)$child['user_id'];

db()->prepare('INSERT INTO deduction_types (user_id, name, amount, unit) VALUES (?, ?, ?, ?)')->execute([$familyUserId, $name, $amount, $unit]);
$allowed = ['/family.php'];
$redirect = in_array($_POST['redirect'] ?? '', $allowed) ? $_POST['redirect'] : "/settings.php?id=$childId";
$_SESSION['flash_success'] = 'Lagt till: ' . $name . ' (' . ($amount > 0 ? '+' : '') . number_format($amount, 2, ',', ' ') . ($unit === 'min' ? ' min' : ' kr') . ').';
header("Location: $redirect");
