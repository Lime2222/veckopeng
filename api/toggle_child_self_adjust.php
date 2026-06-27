<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

$user = requireApiAuth();
if (!verifyCsrf()) jsonOut(['error' => 'CSRF-fel'], 403);

$childId = (int)($_POST['child_id'] ?? 0);
$child   = requireChildOwnership($childId, $user['id']);
if ($child['role'] !== 'owner') jsonOut(['error' => 'Bara ägare kan ändra denna inställning.'], 403);

$new = $child['child_can_self_adjust'] ? 'false' : 'true';
db()->prepare('UPDATE children SET child_can_self_adjust = ? WHERE id = ?')->execute([$new, $childId]);

header('Location: /settings.php?id=' . $childId);
exit;
