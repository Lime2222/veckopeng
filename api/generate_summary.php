<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireApiAuth();
if (!verifyCsrf()) jsonOut(['error' => 'CSRF-fel'], 403);

$body      = json_decode(file_get_contents('php://input'), true);
$childId   = (int)($body['child_id'] ?? 0);
$weekStart = $body['week_start'] ?? '';

if (!$childId || !$weekStart) jsonOut(['error' => 'Ogiltiga parametrar'], 400);
requireChildOwnership($childId, $user['id']);

$weekEnd = (new DateTime($weekStart))->modify('+6 days')->format('Y-m-d');
$totals  = getWeekTotals($childId, $weekStart);

$stmt = db()->prepare('
    SELECT
        (SELECT COUNT(*) FROM daily_logs dl
         JOIN requirements r ON r.id = dl.requirement_id
         WHERE dl.child_id = ? AND dl.log_date BETWEEN ? AND ?
           AND dl.completed = true AND r.frequency = \'daily\')
        +
        (SELECT COUNT(DISTINCT dl.requirement_id) FROM daily_logs dl
         JOIN requirements r ON r.id = dl.requirement_id
         WHERE dl.child_id = ? AND dl.log_date BETWEEN ? AND ?
           AND dl.completed = true AND r.frequency = \'weekly\')
');
$stmt->execute([$childId, $weekStart, $weekEnd, $childId, $weekStart, $weekEnd]);
$reqDone = (int)$stmt->fetchColumn();

$stmt = db()->prepare('
    SELECT COALESCE(SUM(CASE WHEN frequency = \'weekly\' THEN 1 ELSE 7 END), 0)
    FROM requirements
    WHERE user_id = (SELECT user_id FROM children WHERE id = ?)
      AND active = true
      AND NOT EXISTS (
          SELECT 1 FROM child_requirement_exclusions
          WHERE child_id = ? AND requirement_id = requirements.id
      )
');
$stmt->execute([$childId, $childId]);
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
