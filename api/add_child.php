<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel. Försök igen.'; header('Location: /dashboard.php'); exit; }

$name         = trim($_POST['name'] ?? '');
$weeklyAmount = (float)($_POST['weekly_amount'] ?? getSetting('default_weekly_amount', '50'));
$color        = $_POST['avatar_color'] ?? '#6366f1';

if (!$name) { $_SESSION['flash_error'] = 'Ange ett namn.'; header('Location: /dashboard.php'); exit; }
if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) $color = '#6366f1';
if ($weeklyAmount < 0) $weeklyAmount = 0;

// Standardskärmtidspott för nya barn (admin-inställning, tomt/0 = av)
$defScreen = getSetting('default_screen_budget', '');
$screenBudget = ($defScreen !== null && trim($defScreen) !== '' && (int)$defScreen > 0) ? (int)$defScreen : null;

$db   = db();
$stmt = $db->prepare('INSERT INTO children (user_id, name, avatar_color, weekly_amount, screen_budget_minutes) VALUES (?, ?, ?, ?, ?) RETURNING id');
$stmt->execute([$user['id'], $name, $color, $weeklyAmount, $screenBudget]);
$childId = (int)$stmt->fetchColumn();

$db->prepare('INSERT INTO family_members (child_id, user_id, role) VALUES (?, ?, \'owner\')')->execute([$childId, $user['id']]);

// Seed familjegemensamma krav bara om familjen inte redan har några.
// Vilka krav som sås in styrs från admin-sidan (default_requirements).
$stmt = $db->prepare('SELECT COUNT(*) FROM requirements WHERE user_id = ?');
$stmt->execute([$user['id']]);
if ((int)$stmt->fetchColumn() === 0) {
    try {
        $defs = $db->query('SELECT name, type, frequency, weekly_target_minutes FROM default_requirements ORDER BY id')->fetchAll();
        $sort = 0;
        foreach ($defs as $d) {
            $db->prepare('INSERT INTO requirements (user_id, name, sort_order, type, frequency, weekly_target_minutes) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$user['id'], $d['name'], $sort++, $d['type'], $d['frequency'], $d['weekly_target_minutes']]);
        }
    } catch (Throwable $e) { /* default-tabellen saknas tills migrationen körts */ }
}

// Seed avdragstyper bara om familjen inte redan har några (styrs från admin-sidan)
$stmt = $db->prepare('SELECT COUNT(*) FROM deduction_types WHERE user_id = ?');
$stmt->execute([$user['id']]);
if ((int)$stmt->fetchColumn() === 0) {
    try {
        $defs = $db->query('SELECT name, amount, unit FROM default_deduction_types ORDER BY id')->fetchAll();
        foreach ($defs as $d) {
            $db->prepare('INSERT INTO deduction_types (user_id, name, amount, unit) VALUES (?, ?, ?, ?)')
               ->execute([$user['id'], $d['name'], $d['amount'], $d['unit']]);
        }
    } catch (Throwable $e) { /* default-tabellen saknas tills migrationen körts */ }
}

$_SESSION['flash_success'] = "$name är nu upplagd! Standardkrav och avdrag har lagts till.";
header("Location: /child.php?id=$childId");
