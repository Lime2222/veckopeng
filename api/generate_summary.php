<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireApiAuth();
verifyCsrf();

$body      = json_decode(file_get_contents('php://input'), true);
$childId   = (int)($body['child_id'] ?? 0);
$weekStart = $body['week_start'] ?? '';

if (!$childId || !$weekStart) jsonOut(['error' => 'Ogiltiga parametrar'], 400);
requireChildOwnership($childId, $user['id']);

$weekEnd = (new DateTime($weekStart))->modify('+6 days')->format('Y-m-d');
$totals  = getWeekTotals($childId, $weekStart);

$stmt = db()->prepare('
    SELECT COUNT(*) FROM daily_logs
    WHERE child_id = ? AND log_date BETWEEN ? AND ? AND completed = true
');
$stmt->execute([$childId, $weekStart, $weekEnd]);
$reqDone = (int)$stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) * 7 FROM requirements WHERE child_id = ? AND active = true');
$stmt->execute([$childId]);
$reqTotal = (int)$stmt->fetchColumn();

$db   = db();
$stmt = $db->prepare('
    INSERT INTO weekly_summaries
        (child_id, week_start, week_end, base_amount, total_adjustments, final_amount, requirements_completed, requirements_total, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pending\')
    ON CONFLICT (child_id, week_start)
    DO UPDATE SET
        base_amount = EXCLUDED.base_amount,
        total_adjustments = EXCLUDED.total_adjustments,
        final_amount = EXCLUDED.final_amount,
        requirements_completed = EXCLUDED.requirements_completed,
        requirements_total = EXCLUDED.requirements_total,
        generated_at = NOW()
    RETURNING id
');
$stmt->execute([$childId, $weekStart, $weekEnd, $totals['base'], $totals['adjustments'], $totals['final'], $reqDone, $reqTotal]);
$summaryId = (int)$stmt->fetchColumn();

jsonOut(['ok' => true, 'summary_id' => $summaryId, 'final' => $totals['final'], 'final_fmt' => formatKr($totals['final'])]);
