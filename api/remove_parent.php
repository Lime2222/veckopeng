<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }
if (!verifyCsrf()) { $_SESSION['flash_error'] = 'Sessionsfel.'; header('Location: /dashboard.php'); exit; }

$childId       = (int)($_POST['child_id'] ?? 0);
$removeUserId  = (int)($_POST['user_id'] ?? 0);

if (!$childId || !$removeUserId) { header("Location: /settings.php?id=$childId"); exit; }
requireChildOwner($childId, $user['id']); // only owner can remove others

// Cannot remove the owner themselves
$stmt = db()->prepare('SELECT role FROM family_members WHERE child_id = ? AND user_id = ?');
$stmt->execute([$childId, $removeUserId]);
$member = $stmt->fetch();
if (!$member || $member['role'] === 'owner') {
    $_SESSION['flash_error'] = 'Kan inte ta bort ägaren.';
    header("Location: /settings.php?id=$childId"); exit;
}

db()->prepare('DELETE FROM family_members WHERE child_id = ? AND user_id = ?')->execute([$childId, $removeUserId]);
$_SESSION['flash_success'] = 'Förälder borttagen.';
header("Location: /settings.php?id=$childId");
