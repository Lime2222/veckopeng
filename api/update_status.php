<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireApiAuth();
verifyCsrf();

$body      = json_decode(file_get_contents('php://input'), true);
$summaryId = (int)($body['summary_id'] ?? 0);
$status    = $body['status'] ?? '';

if (!$summaryId || !in_array($status, ['pending','paid','sent','owed'])) {
    jsonOut(['error' => 'Ogiltiga parametrar'], 400);
}

$stmt = db()->prepare('
    SELECT ws.id FROM weekly_summaries ws
    JOIN children c ON c.id = ws.child_id
    WHERE ws.id = ? AND c.user_id = ?
');
$stmt->execute([$summaryId, $user['id']]);
if (!$stmt->fetch()) jsonOut(['error' => 'Ej behörig'], 403);

db()->prepare('UPDATE weekly_summaries SET status = ? WHERE id = ?')->execute([$status, $summaryId]);
jsonOut(['ok' => true, 'status' => $status]);
