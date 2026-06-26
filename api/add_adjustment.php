<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireApiAuth();
if (!verifyCsrf()) jsonOut(['error' => 'CSRF-fel'], 403);

$body    = json_decode(file_get_contents('php://input'), true);
$childId   = (int)($body['child_id'] ?? 0);
$typeId    = isset($body['deduction_type_id']) ? (int)$body['deduction_type_id'] : null;
$amount    = (float)($body['amount'] ?? 0);
$desc      = trim($body['description'] ?? '');
$date      = $body['date'] ?? date('Y-m-d');

if (!$childId || !$amount || !$desc) jsonOut(['error' => 'Ogiltiga parametrar'], 400);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonOut(['error' => 'Ogiltigt datum'], 400);
requireChildOwnership($childId, $user['id']);

// Check week lock
$lockWs = weekStart($date);
$lockCheck = db()->prepare('SELECT id FROM week_summaries WHERE child_id = ? AND week_start = ?');
$lockCheck->execute([$childId, $lockWs]);
if ($lockCheck->fetch()) jsonOut(['error' => 'Den här veckan är stängd.'], 403);

if ($typeId) {
    $stmt = db()->prepare('SELECT id FROM deduction_types WHERE id = ? AND user_id = (SELECT user_id FROM children WHERE id = ?)');
    $stmt->execute([$typeId, $childId]);
    if (!$stmt->fetch()) jsonOut(['error' => 'Ogiltig typ'], 400);
}

$stmt = db()->prepare('
    INSERT INTO adjustments (child_id, deduction_type_id, amount, description, log_date)
    VALUES (?, ?, ?, ?, ?)
    RETURNING id
');
$stmt->execute([$childId, $typeId, $amount, $desc, $date]);
$adjId = (int)$stmt->fetchColumn();

$ws     = weekStart($date);
$totals = getWeekTotals($childId, $ws);
jsonOut(['ok' => true, 'adjustment_id' => $adjId, 'final' => $totals['final'], 'final_fmt' => formatKr($totals['final'])]);
