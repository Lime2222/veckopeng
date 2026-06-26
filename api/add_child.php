<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel. Försök igen.'; header('Location: /dashboard.php'); exit; }

$name         = trim($_POST['name'] ?? '');
$weeklyAmount = (float)($_POST['weekly_amount'] ?? 50);
$color        = $_POST['avatar_color'] ?? '#6366f1';

if (!$name) { $_SESSION['flash_error'] = 'Ange ett namn.'; header('Location: /dashboard.php'); exit; }
if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) $color = '#6366f1';
if ($weeklyAmount < 0) $weeklyAmount = 0;

$db   = db();
$stmt = $db->prepare('INSERT INTO children (user_id, name, avatar_color, weekly_amount) VALUES (?, ?, ?, ?) RETURNING id');
$stmt->execute([$user['id'], $name, $color, $weeklyAmount]);
$childId = (int)$stmt->fetchColumn();

$db->prepare('INSERT INTO family_members (child_id, user_id, role) VALUES (?, ?, \'owner\')')->execute([$childId, $user['id']]);

// Seed default requirements
$defaults = [
    ['Städa rummet',  'checkbox', 'weekly', null],
    ['Läsa böcker',   'minutes',  'weekly', 120],   // 2 timmar/vecka
];
$sort = 0;
foreach ($defaults as [$rname, $rtype, $freq, $targetMin]) {
    $db->prepare('INSERT INTO requirements (child_id, name, sort_order, type, frequency, weekly_target_minutes) VALUES (?, ?, ?, ?, ?, ?)')
       ->execute([$childId, $rname, $sort++, $rtype, $freq, $targetMin]);
}

// Seed default deduction types
$defDeductions = [
    ['Ej dukat av tallriken', -1],
    ['Skärmtid över gränsen', -10],
    ['Bonus: Läxa klar tidigt', 10],
];
foreach ($defDeductions as [$rname, $amt]) {
    $db->prepare('INSERT INTO deduction_types (child_id, name, amount) VALUES (?, ?, ?)')->execute([$childId, $rname, $amt]);
}

$_SESSION['flash_success'] = "$name är nu upplagd! Standardkrav och avdrag har lagts till.";
header("Location: /child.php?id=$childId");
