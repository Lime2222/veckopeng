<?php
require_once __DIR__ . '/config.php';

function getChildren(int $userId): array {
    $stmt = db()->prepare('SELECT * FROM children WHERE user_id = ? ORDER BY name');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getChild(int $childId): array|false {
    $stmt = db()->prepare('SELECT * FROM children WHERE id = ?');
    $stmt->execute([$childId]);
    return $stmt->fetch();
}

function getRequirements(int $childId, bool $activeOnly = true): array {
    $sql = 'SELECT * FROM requirements WHERE child_id = ?';
    if ($activeOnly) $sql .= ' AND active = true';
    $sql .= ' ORDER BY sort_order, id';
    $stmt = db()->prepare($sql);
    $stmt->execute([$childId]);
    return $stmt->fetchAll();
}

function getDeductionTypes(int $childId, bool $activeOnly = true): array {
    $sql = 'SELECT * FROM deduction_types WHERE child_id = ?';
    if ($activeOnly) $sql .= ' AND active = true';
    $sql .= ' ORDER BY amount, id';
    $stmt = db()->prepare($sql);
    $stmt->execute([$childId]);
    return $stmt->fetchAll();
}

function getDayLogs(int $childId, string $date): array {
    $stmt = db()->prepare('
        SELECT r.id, r.name,
               COALESCE(dl.completed, false) AS completed
        FROM requirements r
        LEFT JOIN daily_logs dl
               ON dl.requirement_id = r.id
              AND dl.child_id = ?
              AND dl.log_date = ?
        WHERE r.child_id = ? AND r.active = true
        ORDER BY r.sort_order, r.id
    ');
    $stmt->execute([$childId, $date, $childId]);
    return $stmt->fetchAll();
}

function getWeekAdjustments(int $childId, string $weekStart): array {
    $weekEnd = (new DateTime($weekStart))->modify('+6 days')->format('Y-m-d');
    $stmt = db()->prepare('
        SELECT a.*, dt.name AS type_name
        FROM adjustments a
        LEFT JOIN deduction_types dt ON dt.id = a.deduction_type_id
        WHERE a.child_id = ? AND a.log_date BETWEEN ? AND ?
        ORDER BY a.log_date DESC, a.created_at DESC
    ');
    $stmt->execute([$childId, $weekStart, $weekEnd]);
    return $stmt->fetchAll();
}

function getWeekTotals(int $childId, string $weekStart): array {
    $weekEnd = (new DateTime($weekStart))->modify('+6 days')->format('Y-m-d');

    $stmt = db()->prepare('
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM adjustments
        WHERE child_id = ? AND log_date BETWEEN ? AND ?
    ');
    $stmt->execute([$childId, $weekStart, $weekEnd]);
    $adj = (float)$stmt->fetchColumn();

    $stmt = db()->prepare('SELECT weekly_amount FROM children WHERE id = ?');
    $stmt->execute([$childId]);
    $base = (float)$stmt->fetchColumn();

    $stmt = db()->prepare('
        SELECT COUNT(*) FROM daily_logs
        WHERE child_id = ? AND log_date BETWEEN ? AND ? AND completed = true
    ');
    $stmt->execute([$childId, $weekStart, $weekEnd]);
    $completed = (int)$stmt->fetchColumn();

    $stmt = db()->prepare('
        SELECT COUNT(r.id) * 7
        FROM requirements r
        WHERE r.child_id = ? AND r.active = true
    ');
    $stmt->execute([$childId]);
    $total = (int)$stmt->fetchColumn();

    return [
        'base'         => $base,
        'adjustments'  => $adj,
        'final'        => max(0, $base + $adj),
        'req_done'     => $completed,
        'req_total'    => $total,
    ];
}

function getWeeklySummary(int $childId, string $weekStart): array|false {
    $stmt = db()->prepare('SELECT * FROM weekly_summaries WHERE child_id = ? AND week_start = ?');
    $stmt->execute([$childId, $weekStart]);
    return $stmt->fetch();
}

function getWeeklyHistory(int $childId, int $limit = 12): array {
    $stmt = db()->prepare('
        SELECT * FROM weekly_summaries
        WHERE child_id = ?
        ORDER BY week_start DESC
        LIMIT ?
    ');
    $stmt->execute([$childId, $limit]);
    return $stmt->fetchAll();
}

const SWEDISH_DAYS = ['Måndag','Tisdag','Onsdag','Torsdag','Fredag','Lördag','Söndag'];
const SHORT_DAYS   = ['Mån','Tis','Ons','Tor','Fre','Lör','Sön'];
const STATUS_LABELS = [
    'pending' => ['label' => 'Väntar',    'class' => 'bg-amber-100 text-amber-800'],
    'paid'    => ['label' => 'Betald',    'class' => 'bg-green-100 text-green-800'],
    'sent'    => ['label' => 'Skickad',   'class' => 'bg-blue-100 text-blue-800'],
    'owed'    => ['label' => 'Skyldig',   'class' => 'bg-red-100 text-red-800'],
];
