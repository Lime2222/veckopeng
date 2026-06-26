<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireApiAuth();
if (!verifyCsrf()) jsonOut(['error' => 'CSRF-fel'], 403);

$body = json_decode(file_get_contents('php://input'), true);
$childId   = (int)($body['child_id'] ?? 0);
$reqId     = (int)($body['requirement_id'] ?? 0);
$date      = $body['date'] ?? '';
$completed = (bool)($body['completed'] ?? false);

if (!$childId || !$reqId || !$date) jsonOut(['error' => 'Ogiltiga parametrar'], 400);
requireChildOwnership($childId, $user['id']);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonOut(['error' => 'Ogiltigt datum'], 400);

$db = db();

// Check week lock
$lockWs = weekStart($date);
$lockCheck = $db->prepare('SELECT id FROM weekly_summaries WHERE child_id = ? AND week_start = ?');
$lockCheck->execute([$childId, $lockWs]);
if ($lockCheck->fetch()) jsonOut(['error' => 'Den här veckan är stängd.'], 403);

$freq = $db->prepare('SELECT frequency FROM requirements WHERE id = ? AND user_id = (SELECT user_id FROM children WHERE id = ?)');
$freq->execute([$reqId, $childId]);
$requirement = $freq->fetch();
$isWeekly = $requirement && $requirement['frequency'] === 'weekly';

if ($isWeekly) {
    $ws = weekStart($date);
    $we = (new DateTime($ws))->modify('+6 days')->format('Y-m-d');
    if ($completed) {
        // Bocka av för just den dag hon valde
        $db->prepare('
            INSERT INTO daily_logs (child_id, requirement_id, log_date, completed)
            VALUES (?, ?, ?, true)
            ON CONFLICT (child_id, requirement_id, log_date)
            DO UPDATE SET completed = true
        ')->execute([$childId, $reqId, $date]);
    } else {
        // Avbocka hela veckan
        $db->prepare('
            UPDATE daily_logs SET completed = false
            WHERE child_id = ? AND requirement_id = ? AND log_date BETWEEN ? AND ?
        ')->execute([$childId, $reqId, $ws, $we]);
    }
} else {
    $db->prepare('
        INSERT INTO daily_logs (child_id, requirement_id, log_date, completed)
        VALUES (?, ?, ?, ?)
        ON CONFLICT (child_id, requirement_id, log_date)
        DO UPDATE SET completed = EXCLUDED.completed
    ')->execute([$childId, $reqId, $date, $completed ? 'true' : 'false']);
}

$ws      = weekStart($date);
$totals  = getWeekTotals($childId, $ws);
jsonOut(['ok' => true, 'final' => $totals['final'], 'final_fmt' => formatKr($totals['final'])]);
