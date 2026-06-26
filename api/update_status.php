<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireApiAuth();
if (!verifyCsrf()) jsonOut(['error' => 'CSRF-fel'], 403);

$body      = json_decode(file_get_contents('php://input'), true);
$summaryId = (int)($body['summary_id'] ?? 0);
$status    = $body['status'] ?? '';

if (!$summaryId || !in_array($status, ['pending','paid','owed'])) {
    jsonOut(['error' => 'Ogiltiga parametrar'], 400);
}

$stmt = db()->prepare('
    SELECT ws.id FROM weekly_summaries ws
    JOIN family_members fm ON fm.child_id = ws.child_id AND fm.user_id = ?
    WHERE ws.id = ?
');
$stmt->execute([$user['id'], $summaryId]);
if (!$stmt->fetch()) jsonOut(['error' => 'Ej behörig'], 403);

db()->prepare('UPDATE weekly_summaries SET status = ? WHERE id = ?')->execute([$status, $summaryId]);
jsonOut(['ok' => true, 'status' => $status]);
