<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId = (int)($_POST['child_id'] ?? 0);
$reqId   = (int)($_POST['requirement_id'] ?? 0);

if (!$childId || !$reqId) { header("Location: /settings.php?id=$childId"); exit; }
requireChildOwnership($childId, $user['id']);

// Toggle: if excluded → remove exclusion, if not excluded → add exclusion
$stmt = db()->prepare('SELECT 1 FROM child_requirement_exclusions WHERE child_id = ? AND requirement_id = ?');
$stmt->execute([$childId, $reqId]);

if ($stmt->fetch()) {
    db()->prepare('DELETE FROM child_requirement_exclusions WHERE child_id = ? AND requirement_id = ?')
        ->execute([$childId, $reqId]);
} else {
    db()->prepare('INSERT INTO child_requirement_exclusions (child_id, requirement_id) VALUES (?, ?)')
        ->execute([$childId, $reqId]);
}

header("Location: /settings.php?id=$childId");
