<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /family.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /family.php'); exit; }

$childId   = (int)($_POST['child_id'] ?? 0);
$policy    = $_POST['policy'] ?? 'none';
$penalty   = (float)str_replace(',', '.', $_POST['penalty'] ?? '0');
$screenFee = (float)str_replace(',', '.', $_POST['screen_fee'] ?? '0');
if ($screenFee < 0) $screenFee = 0;

if (!in_array($policy, ['none', 'all', 'percent', 'fixed'], true)) $policy = 'none';
if ($penalty < 0) $penalty = 0;
if ($policy === 'percent' && $penalty > 100) $penalty = 100;
if (!in_array($policy, ['percent', 'fixed'], true)) $penalty = 0;

if (($policy === 'percent' || $policy === 'fixed') && $penalty <= 0) {
    $_SESSION['flash_error'] = 'Ange hur mycket som ska dras av per missat krav.';
    header('Location: /family.php'); exit;
}

$child = requireChildOwnership($childId, $user['id']);
if ($child['role'] === 'child') {
    $_SESSION['flash_error'] = 'Endast föräldrar kan ändra reglerna.';
    header('Location: /family.php'); exit;
}

db()->prepare('UPDATE users SET req_policy = ?, req_penalty = ?, screen_overage_fee = ? WHERE id = ?')
    ->execute([$policy, $penalty, $screenFee, (int)$child['user_id']]);

$_SESSION['flash_success'] = 'Veckopengsreglerna är sparade.';
header('Location: /family.php');
