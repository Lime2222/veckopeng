<?php
require_once __DIR__ . '/config.php';

function getChildren(int $userId): array {
    $stmt = db()->prepare('
        SELECT c.*, fm.role, fm.sort_order
        FROM children c
        JOIN family_members fm ON fm.child_id = c.id AND fm.user_id = ?
        ORDER BY fm.sort_order ASC, c.name ASC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getChildMembers(int $childId): array {
    $stmt = db()->prepare('
        SELECT u.id, u.name, u.email, fm.role, fm.created_at
        FROM family_members fm
        JOIN users u ON u.id = fm.user_id
        WHERE fm.child_id = ?
        ORDER BY fm.role DESC, u.name
    ');
    $stmt->execute([$childId]);
    return $stmt->fetchAll();
}

function getPendingInvitations(int $childId): array {
    $stmt = db()->prepare('
        SELECT i.*, u.name AS invited_by_name
        FROM invitations i
        JOIN users u ON u.id = i.invited_by
        WHERE i.child_id = ? AND i.accepted = false AND i.expires_at > NOW()
        ORDER BY i.created_at DESC
    ');
    $stmt->execute([$childId]);
    return $stmt->fetchAll();
}

function getChild(int $childId): array|false {
    $stmt = db()->prepare('SELECT * FROM children WHERE id = ?');
    $stmt->execute([$childId]);
    return $stmt->fetch();
}

function getRequirements(int $childId, bool $activeOnly = true): array {
    $sql = 'SELECT * FROM requirements
            WHERE user_id = (SELECT user_id FROM children WHERE id = ?)
              AND NOT EXISTS (
                  SELECT 1 FROM child_requirement_exclusions
                  WHERE child_id = ? AND requirement_id = requirements.id
              )';
    if ($activeOnly) $sql .= ' AND active = true';
    $sql .= ' ORDER BY sort_order, id';
    $stmt = db()->prepare($sql);
    $stmt->execute([$childId, $childId]);
    return $stmt->fetchAll();
}

function getRequirementsWithExclusions(int $childId): array {
    $stmt = db()->prepare('
        SELECT r.*,
               EXISTS (
                   SELECT 1 FROM child_requirement_exclusions
                   WHERE child_id = ? AND requirement_id = r.id
               ) AS excluded
        FROM requirements r
        WHERE r.user_id = (SELECT user_id FROM children WHERE id = ?)
          AND r.active = true
        ORDER BY r.sort_order, r.id
    ');
    $stmt->execute([$childId, $childId]);
    return $stmt->fetchAll();
}

function getDeductionTypes(int $childId, bool $activeOnly = true): array {
    $sql = 'SELECT * FROM deduction_types WHERE user_id = (SELECT user_id FROM children WHERE id = ?)';
    if ($activeOnly) $sql .= ' AND active = true';
    $sql .= ' ORDER BY amount, id';
    $stmt = db()->prepare($sql);
    $stmt->execute([$childId]);
    return $stmt->fetchAll();
}

function getDayLogs(int $childId, string $date): array {
    $ws = weekStart($date);
    $we = (new DateTime($ws))->modify('+6 days')->format('Y-m-d');

    $stmt = db()->prepare('
        SELECT r.id, r.name, r.frequency, r.type, r.weekly_target_minutes,
               CASE
                   WHEN r.type = \'minutes\' THEN false
                   WHEN r.frequency = \'weekly\' THEN
                       EXISTS (
                           SELECT 1 FROM daily_logs dl2
                           WHERE dl2.requirement_id = r.id
                             AND dl2.child_id = ?
                             AND dl2.log_date BETWEEN ? AND ?
                             AND dl2.completed = true
                       )
                   ELSE COALESCE(dl.completed, false)
               END AS completed,
               COALESCE(dl.minutes, 0) AS minutes_today,
               COALESCE((
                   SELECT SUM(dl3.minutes) FROM daily_logs dl3
                   WHERE dl3.requirement_id = r.id
                     AND dl3.child_id = ?
                     AND dl3.log_date BETWEEN ? AND ?
               ), 0) AS minutes_week
        FROM requirements r
        LEFT JOIN daily_logs dl
               ON dl.requirement_id = r.id
              AND dl.child_id = ?
              AND dl.log_date = ?
        WHERE r.user_id = (SELECT user_id FROM children WHERE id = ?) AND r.active = true
          AND NOT EXISTS (
              SELECT 1 FROM child_requirement_exclusions
              WHERE child_id = ? AND requirement_id = r.id
          )
        ORDER BY r.sort_order, r.id
    ');
    $stmt->execute([$childId, $ws, $we, $childId, $ws, $we, $childId, $date, $childId, $childId]);
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

function getFamilyPolicy(int $childId): array {
    try {
        $stmt = db()->prepare('SELECT req_policy, req_penalty, screen_overage_fee FROM users WHERE id = (SELECT user_id FROM children WHERE id = ?)');
        $stmt->execute([$childId]);
        $row = $stmt->fetch();
        return [
            'policy'     => $row['req_policy'] ?? 'none',
            'penalty'    => (float)($row['req_penalty'] ?? 0),
            'screen_fee' => (float)($row['screen_overage_fee'] ?? 0),
        ];
    } catch (Throwable $e) {
        // Kolumnerna saknas tills migrationen körts - bete dig som tidigare
        return ['policy' => 'none', 'penalty' => 0.0, 'screen_fee' => 0.0];
    }
}

function formatMin(int $m): string {
    $h = intdiv(abs($m), 60);
    $r = abs($m) % 60;
    $s = $h > 0 ? ($h . ' h' . ($r ? ' ' . $r . ' min' : '')) : ($r . ' min');
    return ($m < 0 ? '−' : '') . $s;
}

function getWeekTotals(int $childId, string $weekStart): array {
    $weekEnd = (new DateTime($weekStart))->modify('+6 days')->format('Y-m-d');

    $stmt = db()->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM adjustments
        WHERE child_id = ? AND log_date BETWEEN ? AND ? AND unit = 'kr'
    ");
    $stmt->execute([$childId, $weekStart, $weekEnd]);
    $adj = (float)$stmt->fetchColumn();

    // Skärmtidsjusteringar (bonus/avdrag i minuter) påverkar veckans pott
    $stmt = db()->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM adjustments
        WHERE child_id = ? AND log_date BETWEEN ? AND ? AND unit = 'min'
    ");
    $stmt->execute([$childId, $weekStart, $weekEnd]);
    $screenAdj = (int)round((float)$stmt->fetchColumn());

    $stmt = db()->prepare('SELECT weekly_amount, screen_budget_minutes FROM children WHERE id = ?');
    $stmt->execute([$childId]);
    $childRow = $stmt->fetch();
    $base = (float)($childRow['weekly_amount'] ?? 0);
    $screenBudget  = $childRow['screen_budget_minutes'];
    $screenEnabled = $screenBudget !== null;

    // Missade krav räknas först när tillfället passerat: en daglig bock är
    // missad när dagen är slut, vecko-/minutkrav när hela veckan är slut.
    $today       = date('Y-m-d');
    $weekOver    = $today > $weekEnd;
    $elapsedDays = $weekOver ? 7
        : max(0, min(7, (int)round((strtotime($today) - strtotime($weekStart)) / 86400)));
    $dailyCutoff = $weekOver ? $weekEnd : date('Y-m-d', strtotime($today . ' -1 day'));

    $reqs  = getRequirements($childId);
    $total = 0;
    $completed = 0;
    $missed = 0;
    foreach ($reqs as $req) {
        if ($req['type'] === 'minutes') {
            $total += 1;
            $target = (int)($req['weekly_target_minutes'] ?? 0);
            $done = false;
            if ($target > 0) {
                $s = db()->prepare('SELECT COALESCE(SUM(minutes),0) FROM daily_logs WHERE requirement_id=? AND child_id=? AND log_date BETWEEN ? AND ?');
                $s->execute([$req['id'], $childId, $weekStart, $weekEnd]);
                $done = (int)$s->fetchColumn() >= $target;
            }
            if ($done) $completed += 1;
            elseif ($weekOver) $missed += 1;
        } elseif ($req['frequency'] === 'weekly') {
            $total += 1;
            $s = db()->prepare('SELECT COUNT(*) FROM daily_logs WHERE requirement_id=? AND child_id=? AND log_date BETWEEN ? AND ? AND completed=true');
            $s->execute([$req['id'], $childId, $weekStart, $weekEnd]);
            $done = (int)$s->fetchColumn() > 0;
            if ($done) $completed += 1;
            elseif ($weekOver) $missed += 1;
        } else {
            $total += 7;
            $s = db()->prepare('SELECT COUNT(*) FROM daily_logs WHERE requirement_id=? AND child_id=? AND log_date BETWEEN ? AND ? AND completed=true');
            $s->execute([$req['id'], $childId, $weekStart, $weekEnd]);
            $completed += (int)$s->fetchColumn();
            if ($elapsedDays > 0 && $dailyCutoff >= $weekStart) {
                $s = db()->prepare('SELECT COUNT(*) FROM daily_logs WHERE requirement_id=? AND child_id=? AND log_date BETWEEN ? AND ? AND completed=true');
                $s->execute([$req['id'], $childId, $weekStart, $dailyCutoff]);
                $missed += max(0, $elapsedDays - (int)$s->fetchColumn());
            }
        }
    }

    // Familjens regel för missade krav
    $cfg = getFamilyPolicy($childId);
    $penalty = 0.0;
    if ($missed > 0 && $base > 0) {
        switch ($cfg['policy']) {
            case 'all':     $penalty = $base; break;
            case 'percent': $penalty = min($base, round($base * $cfg['penalty'] / 100, 2) * $missed); break;
            case 'fixed':   $penalty = min($base, $cfg['penalty'] * $missed); break;
        }
        $penalty = min($base, $penalty);
    }

    // Skärmtid: använd tid mot veckans pott (pott = budget + minutjusteringar)
    $screenUsed = 0;
    $screenPool = 0;
    $screenOver = 0;
    $screenFee  = 0.0;
    if ($screenEnabled) {
        try {
            $s = db()->prepare('SELECT COALESCE(SUM(minutes),0) FROM screen_logs WHERE child_id = ? AND log_date BETWEEN ? AND ?');
            $s->execute([$childId, $weekStart, $weekEnd]);
            $screenUsed = (int)$s->fetchColumn();
        } catch (Throwable $e) { /* tabellen saknas tills migrationen körts */ }
        $screenPool = max(0, (int)$screenBudget + $screenAdj);
        $screenOver = max(0, $screenUsed - $screenPool);
        if ($screenOver > 0 && $cfg['screen_fee'] > 0) {
            // kr per påbörjad 10-minutersperiod över potten
            $screenFee = ceil($screenOver / 10) * $cfg['screen_fee'];
        }
    }

    return [
        'base'           => $base,
        'adjustments'    => $adj,
        'penalty'        => $penalty,
        'missed'         => $missed,
        'policy'         => $cfg['policy'],
        'screen_enabled' => $screenEnabled,
        'screen_budget'  => $screenEnabled ? (int)$screenBudget : 0,
        'screen_adj'     => $screenAdj,
        'screen_pool'    => $screenPool,
        'screen_used'    => $screenUsed,
        'screen_over'    => $screenOver,
        'screen_fee'     => $screenFee,
        'final'          => max(0, $base - $penalty - $screenFee + $adj),
        'req_done'       => $completed,
        'req_total'      => $total,
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

function getPaymentTotalsPerParent(int $userId): array {
    $stmt = db()->prepare('
        SELECT u.id, u.name, SUM(ws.final_amount) AS total
        FROM weekly_summaries ws
        JOIN users u ON u.id = ws.paid_by_user_id
        WHERE ws.child_id IN (
            SELECT child_id FROM family_members WHERE user_id = ?
        )
        AND ws.status = \'paid\'
        AND ws.paid_by_user_id IS NOT NULL
        GROUP BY u.id, u.name
        ORDER BY total DESC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

const SWEDISH_DAYS = ['Måndag','Tisdag','Onsdag','Torsdag','Fredag','Lördag','Söndag'];
const SHORT_DAYS   = ['Mån','Tis','Ons','Tor','Fre','Lör','Sön'];
const STATUS_LABELS = [
    'pending' => ['label' => 'Väntar',  'class' => 'bg-amber-100 text-amber-800'],
    'paid'    => ['label' => 'Betald',  'class' => 'bg-green-100 text-green-800'],
    'owed'    => ['label' => 'Skyldig', 'class' => 'bg-red-100 text-red-800'],
];
