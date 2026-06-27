<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';

$user = requireApiAuth();

$redirect = $_POST['redirect'] ?? '/family.php';
if (!preg_match('#^/[a-zA-Z0-9/_\-.?=&]*$#', $redirect)) $redirect = '/family.php';

if (!verifyCsrf()) { $_SESSION['flash_error'] = 'CSRF-fel.'; header('Location: ' . $redirect); exit; }

$childId   = (int)($_POST['child_id'] ?? 0);
$direction = $_POST['direction'] ?? '';

if (!$childId || !in_array($direction, ['up', 'down'])) {
    header('Location: ' . $redirect);
    exit;
}

requireChildOwnership($childId, $user['id']);

$children = getChildren($user['id']);

// Initialize sort_order if all are equal (first time reordering)
$orders = array_column($children, 'sort_order');
if (count(array_unique($orders)) === 1) {
    $db = db();
    foreach ($children as $i => $c) {
        $db->prepare('UPDATE family_members SET sort_order = ? WHERE child_id = ? AND user_id = ?')
           ->execute([$i, $c['id'], $user['id']]);
    }
    $children = getChildren($user['id']);
}

// Find current index
$idx = null;
foreach ($children as $i => $c) {
    if ($c['id'] === $childId) { $idx = $i; break; }
}

if ($idx === null) { header('Location: ' . $redirect); exit; }

$swapIdx = $direction === 'up' ? $idx - 1 : $idx + 1;
if ($swapIdx < 0 || $swapIdx >= count($children)) {
    header('Location: ' . $redirect);
    exit;
}

// Swap sort_orders
$db = db();
$orderA = (int)$children[$idx]['sort_order'];
$orderB = (int)$children[$swapIdx]['sort_order'];

$db->prepare('UPDATE family_members SET sort_order = ? WHERE child_id = ? AND user_id = ?')
   ->execute([$orderB, $children[$idx]['id'], $user['id']]);
$db->prepare('UPDATE family_members SET sort_order = ? WHERE child_id = ? AND user_id = ?')
   ->execute([$orderA, $children[$swapIdx]['id'], $user['id']]);

header('Location: ' . $redirect);
exit;
