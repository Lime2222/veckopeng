<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireApiAuth();
if (!verifyCsrf()) jsonOut(['error' => 'CSRF-fel'], 403);

$body    = json_decode(file_get_contents('php://input'), true);
$childId = (int)($body['child_id'] ?? 0);
$reqId   = (int)($body['requirement_id'] ?? 0);
$date    = $body['date'] ?? '';
$minutes = (int)($body['minutes'] ?? 0);

if (!$childId || !$reqId || !$date) jsonOut(['error' => 'Ogiltiga parametrar'], 400);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonOut(['error' => 'Ogiltigt datum'], 400);
if ($minutes < 0) $minutes = 0;

requireChildOwnership($childId, $user['id']);

// Check week lock
$lockWs = weekStart($date);
$lockCheck = db()->prepare('SELECT id FROM week_summaries WHERE child_id = ? AND week_start = ?');
$lockCheck->execute([$childId, $lockWs]);
if ($lockCheck->fetch()) jsonOut(['error' => 'Den här veckan är stängd.'], 403);

$stmt = db()->prepare('SELECT type, weekly_target_minutes FROM requirements WHERE id = ? AND user_id = (SELECT user_id FROM children WHERE id = ?)');
$stmt->execute([$reqId, $childId]);
$req = $stmt->fetch();
if (!$req || $req['type'] !== 'minutes') jsonOut(['error' => 'Inte ett minutkrav'], 400);

db()->prepare('
    INSERT INTO daily_logs (child_id, requirement_id, log_date, minutes, completed)
    VALUES (?, ?, ?, ?, false)
    ON CONFLICT (child_id, requirement_id, log_date)
    DO UPDATE SET minutes = EXCLUDED.minutes
')->execute([$childId, $reqId, $date, $minutes]);

$ws  = weekStart($date);
$we  = (new DateTime($ws))->modify('+6 days')->format('Y-m-d');
$stmt = db()->prepare('SELECT COALESCE(SUM(minutes),0) FROM daily_logs WHERE requirement_id=? AND child_id=? AND log_date BETWEEN ? AND ?');
$stmt->execute([$reqId, $childId, $ws, $we]);
$weekTotal = (int)$stmt->fetchColumn();
$target    = (int)($req['weekly_target_minutes'] ?? 0);

jsonOut([
    'ok'           => true,
    'minutes_today' => $minutes,
    'minutes_week'  => $weekTotal,
    'target'        => $target,
    'completed'     => $target > 0 && $weekTotal >= $target,
]);
