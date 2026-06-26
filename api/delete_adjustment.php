<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireApiAuth();
if (!verifyCsrf()) jsonOut(['error' => 'CSRF-fel'], 403);

$body  = json_decode(file_get_contents('php://input'), true);
$adjId = (int)($body['adjustment_id'] ?? 0);
$childId = (int)($body['child_id'] ?? 0);

if (!$adjId || !$childId) jsonOut(['error' => 'Ogiltiga parametrar'], 400);
requireChildOwnership($childId, $user['id']);

$stmt = db()->prepare('SELECT * FROM adjustments WHERE id = ? AND child_id = ?');
$stmt->execute([$adjId, $childId]);
$adj = $stmt->fetch();
if (!$adj) jsonOut(['error' => 'Hittades inte'], 404);

// Check week lock
$lockWs = weekStart($adj['log_date']);
$lockCheck = db()->prepare('SELECT id FROM week_summaries WHERE child_id = ? AND week_start = ?');
$lockCheck->execute([$childId, $lockWs]);
if ($lockCheck->fetch()) jsonOut(['error' => 'Den här veckan är stängd.'], 403);

db()->prepare('DELETE FROM adjustments WHERE id = ?')->execute([$adjId]);

$ws     = weekStart($adj['log_date']);
$totals = getWeekTotals($childId, $ws);
jsonOut(['ok' => true, 'final' => $totals['final'], 'final_fmt' => formatKr($totals['final'])]);
