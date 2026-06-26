<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireApiAuth();
verifyCsrf();

$body = json_decode(file_get_contents('php://input'), true);
$childId   = (int)($body['child_id'] ?? 0);
$reqId     = (int)($body['requirement_id'] ?? 0);
$date      = $body['date'] ?? '';
$completed = (bool)($body['completed'] ?? false);

if (!$childId || !$reqId || !$date) jsonOut(['error' => 'Ogiltiga parametrar'], 400);
requireChildOwnership($childId, $user['id']);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonOut(['error' => 'Ogiltigt datum'], 400);

$db = db();
$stmt = $db->prepare('
    INSERT INTO daily_logs (child_id, requirement_id, log_date, completed)
    VALUES (?, ?, ?, ?)
    ON CONFLICT (child_id, requirement_id, log_date)
    DO UPDATE SET completed = EXCLUDED.completed
');
$stmt->execute([$childId, $reqId, $date, $completed ? 'true' : 'false']);

$ws      = weekStart($date);
$totals  = getWeekTotals($childId, $ws);
jsonOut(['ok' => true, 'final' => $totals['final'], 'final_fmt' => formatKr($totals['final'])]);
