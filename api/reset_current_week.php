<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireApiAuth();
if (!verifyCsrf()) jsonOut(['error' => 'CSRF-fel'], 403);

$childId = (int)($_POST['child_id'] ?? 0);
$member  = requireChildOwnership($childId, $user['id']);
if ($member['role'] !== 'owner') jsonOut(['error' => 'Bara ägare kan återställa veckan.'], 403);

$ws = weekStart();

db()->prepare('DELETE FROM weekly_summaries WHERE child_id = ? AND week_start = ?')
   ->execute([$childId, $ws]);

$_SESSION['flash_success'] = 'Vecka ' . date('W') . ' är återställd och öppen igen.';
header('Location: /settings.php?id=' . $childId);
exit;
