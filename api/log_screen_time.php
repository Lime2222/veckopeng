<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireApiAuth();
if (!verifyCsrf()) jsonOut(['error' => 'CSRF-fel'], 403);

$body     = json_decode(file_get_contents('php://input'), true);
$childId  = (int)($body['child_id'] ?? 0);
$date     = $body['date'] ?? '';
$minutes  = (int)($body['minutes'] ?? 0);
$category = $body['category'] ?? '';

if (!$childId || !$date) jsonOut(['error' => 'Ogiltiga parametrar'], 400);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonOut(['error' => 'Ogiltigt datum'], 400);
if (!isset(SCREEN_CATS[$category])) jsonOut(['error' => 'Ogiltig kategori'], 400);
if ($minutes < 0) $minutes = 0;

$childMember = requireChildOwnership($childId, $user['id']);
if ($childMember['role'] === 'child' && !$childMember['child_can_self_report']) {
    jsonOut(['error' => 'Du har inte rättighet att logga skärmtid.'], 403);
}
$budgets = getScreenBudgets($childId);
if (!isset($budgets[$category])) {
    jsonOut(['error' => 'Kategorin är inte aktiverad för det här barnet.'], 400);
}

// Check week lock
$lockWs = weekStart($date);
$lockCheck = db()->prepare('SELECT id FROM weekly_summaries WHERE child_id = ? AND week_start = ?');
$lockCheck->execute([$childId, $lockWs]);
if ($lockCheck->fetch()) jsonOut(['error' => 'Den här veckan är stängd.'], 403);

db()->prepare('
    INSERT INTO screen_logs (child_id, log_date, category, minutes)
    VALUES (?, ?, ?, ?)
    ON CONFLICT (child_id, log_date, category)
    DO UPDATE SET minutes = EXCLUDED.minutes
')->execute([$childId, $date, $category, $minutes]);

$totals = getWeekTotals($childId, weekStart($date));
jsonOut([
    'ok'            => true,
    'minutes_today' => $minutes,
    'screen_used'   => $totals['screen_used'],
    'screen_pool'   => $totals['screen_pool'],
    'screen_over'   => $totals['screen_over'],
    'screen_fee'    => $totals['screen_fee'],
    'final'         => $totals['final'],
    'final_fmt'     => formatKr($totals['final']),
]);
