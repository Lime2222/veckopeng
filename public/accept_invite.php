<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

startSession();

$token = $_GET['token'] ?? $_SESSION['pending_invite'] ?? '';

if (!$token) {
    header('Location: /dashboard.php'); exit;
}

// Validate token
$stmt = db()->prepare('
    SELECT i.*, c.name AS child_name, u.name AS inviter_name
    FROM invitations i
    JOIN children c ON c.id = i.child_id
    JOIN users u ON u.id = i.invited_by
    WHERE i.token = ? AND i.accepted = false AND i.expires_at > NOW()
');
$stmt->execute([$token]);
$invite = $stmt->fetch();

if (!$invite) {
    $expired = true;
} else {
    $expired = false;
}

// If not logged in, save token and redirect to login
if (empty($_SESSION['user_id'])) {
    $_SESSION['pending_invite'] = $token;
    header('Location: /index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

if (!$expired) {
    // Check not already a member
    $stmt = db()->prepare('SELECT id FROM family_members WHERE child_id = ? AND user_id = ?');
    $stmt->execute([$invite['child_id'], $userId]);
    $alreadyMember = $stmt->fetch();

    if (!$alreadyMember) {
        db()->prepare('
            INSERT INTO family_members (child_id, user_id, role)
            VALUES (?, ?, \'parent\')
            ON CONFLICT (child_id, user_id) DO NOTHING
        ')->execute([$invite['child_id'], $userId]);

        db()->prepare('UPDATE invitations SET accepted = true WHERE token = ?')->execute([$token]);
    }

    unset($_SESSION['pending_invite']);
    $_SESSION['flash_success'] = 'Du har nu tillgång till ' . $invite['child_name'] . '!';
    header('Location: /child.php?id=' . $invite['child_id']);
    exit;
}

require_once dirname(__DIR__) . '/src/layout.php';
pageHead('Inbjudan ogiltig');
pageNav($_SESSION['user_name'] ?? 'Okänd');
?>
<main class="max-w-lg mx-auto px-4 py-12 text-center">
  <span class="text-5xl block mb-4">⏰</span>
  <h1 class="text-2xl font-bold text-gray-900 mb-2">Inbjudan har utgått</h1>
  <p class="text-gray-500 mb-6">Denna inbjudningslänk är inte längre giltig. Be den andra föräldern skicka en ny.</p>
  <a href="/dashboard.php" class="inline-block bg-indigo-600 text-white font-semibold px-6 py-3 rounded-xl hover:bg-indigo-700 transition-colors">
    Till startsidan
  </a>
</main>
<?php pageFoot(); ?>
