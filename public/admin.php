<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/layout.php';

$user = requireAdmin();

// Bocka av/på ett förslag i Förslagslådan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suggestion_done']) && verifyCsrf()) {
    db()->prepare('UPDATE suggestions SET done = NOT done WHERE id = ?')
        ->execute([(int)$_POST['suggestion_done']]);
    header('Location: /admin.php'); exit;
}

function fmtTs(?string $ts): string {
    if (!$ts) return '–';
    return date('Y-m-d H:i', strtotime($ts));
}

// ── Översikt ────────────────────────────────────────────────────────────────
$stats = db()->query("
    SELECT
        (SELECT COUNT(*) FROM users)                                            AS users,
        (SELECT COUNT(*) FROM children)                                         AS children,
        (SELECT COUNT(*) FROM family_members)                                   AS memberships,
        (SELECT COUNT(*) FROM weekly_summaries)                                  AS summaries,
        (SELECT COUNT(*) FROM remember_tokens WHERE expires_at > NOW())          AS active_tokens,
        (SELECT COUNT(*) FROM login_attempts WHERE NOT success
             AND created_at > NOW() - INTERVAL '7 days')                         AS failed_7d
")->fetch();

// ── Konton ──────────────────────────────────────────────────────────────────
$users = db()->query("
    SELECT u.id, u.name, u.email, u.created_at,
        (SELECT COUNT(*) FROM family_members fm WHERE fm.user_id = u.id AND fm.role = 'owner')  AS owner_of,
        (SELECT COUNT(*) FROM family_members fm WHERE fm.user_id = u.id AND fm.role = 'parent') AS parent_of,
        (SELECT COUNT(*) FROM family_members fm WHERE fm.user_id = u.id AND fm.role = 'child')  AS child_of,
        (SELECT MAX(la.created_at) FROM login_attempts la WHERE la.email = u.email AND la.success) AS last_login
    FROM users u
    ORDER BY u.created_at DESC
")->fetchAll();

// ── Familjer: barn som delar medlemmar grupperas ihop till en familj ────────
$children = db()->query("
    SELECT c.id, c.name, c.avatar_color, c.weekly_amount, c.created_at,
           u.name AS owner_name, u.email AS owner_email
    FROM children c
    JOIN users u ON u.id = c.user_id
    ORDER BY u.email, c.name
")->fetchAll();

$links = db()->query("
    SELECT fm.child_id, fm.user_id, fm.role, u.name, u.email
    FROM family_members fm
    JOIN users u ON u.id = fm.user_id
")->fetchAll();

// Union-find: varje barn och användare är en nod, medlemskap är kanter.
// Sammanhängande grupper = en familj, oavsett vem som äger vilket barn.
$uf = [];
$find = function (string $x) use (&$uf, &$find): string {
    if (!isset($uf[$x])) $uf[$x] = $x;
    if ($uf[$x] !== $x) $uf[$x] = $find($uf[$x]);
    return $uf[$x];
};
foreach ($links as $l) {
    $ra = $find('c' . $l['child_id']);
    $rb = $find('u' . $l['user_id']);
    if ($ra !== $rb) $uf[$ra] = $rb;
}

$childName = [];
foreach ($children as $c) $childName[$c['id']] = $c['name'];

$families = [];
foreach ($children as $c) {
    $families[$find('c' . $c['id'])]['children'][] = $c;
}
foreach ($links as $l) {
    $root = $find('c' . $l['child_id']);
    if (!isset($families[$root])) continue;
    $m = &$families[$root]['members'][$l['user_id']];
    $m['name']    = $l['name'];
    $m['email']   = $l['email'];
    $m['roles'][] = ['role' => $l['role'], 'child' => $childName[$l['child_id']] ?? '?'];
    unset($m);
}

// Vuxna först i medlemslistan, ägare överst
$roleOrder = ['owner' => 0, 'parent' => 1, 'child' => 2];
foreach ($families as &$fam) {
    $fam['members'] = $fam['members'] ?? [];
    foreach ($fam['members'] as &$m) {
        usort($m['roles'], fn($a, $b) => ($roleOrder[$a['role']] ?? 9) <=> ($roleOrder[$b['role']] ?? 9));
    }
    unset($m);
    uasort($fam['members'], fn($a, $b) =>
        ($roleOrder[$a['roles'][0]['role']] ?? 9) <=> ($roleOrder[$b['roles'][0]['role']] ?? 9)
            ?: strcasecmp($a['name'], $b['name']));
    // Familjens namn: de vuxna medlemmarna
    $adults = array_filter($fam['members'], fn($m) => in_array($m['roles'][0]['role'], ['owner', 'parent'], true));
    $fam['label'] = $adults ? implode(' & ', array_column($adults, 'name')) : 'Familj';
}
unset($fam);

// Största familjen överst
uasort($families, fn($a, $b) => count($b['children']) <=> count($a['children']));

// ── Inloggningsförsök ───────────────────────────────────────────────────────
$attempts = db()->query("
    SELECT * FROM login_attempts ORDER BY created_at DESC LIMIT 30
")->fetchAll();

$failedTop = db()->query("
    SELECT email, COUNT(*) AS n, MAX(created_at) AS last_try
    FROM login_attempts
    WHERE NOT success AND created_at > NOW() - INTERVAL '7 days'
    GROUP BY email
    ORDER BY n DESC
    LIMIT 10
")->fetchAll();

// ── Förslagslådan ───────────────────────────────────────────────────────────
$suggestions = db()->query("
    SELECT s.id, s.message, s.done, s.created_at, u.name AS user_name, u.email AS user_email
    FROM suggestions s
    LEFT JOIN users u ON u.id = s.user_id
    ORDER BY s.done ASC, s.created_at DESC
")->fetchAll();

// ── Väntande inbjudningar ───────────────────────────────────────────────────
$invites = db()->query("
    SELECT i.created_at, i.expires_at, i.role, c.name AS child_name, u.name AS invited_by_name
    FROM invitations i
    JOIN children c ON c.id = i.child_id
    JOIN users u ON u.id = i.invited_by
    WHERE NOT i.accepted AND i.expires_at > NOW()
    ORDER BY i.created_at DESC
")->fetchAll();

const ROLE_LABELS = [
    'owner'  => ['Ägare',    'bg-indigo-100 text-indigo-800'],
    'parent' => ['Förälder', 'bg-blue-100 text-blue-800'],
    'child'  => ['Barn',     'bg-amber-100 text-amber-800'],
];

pageHead('Admin');
pageNav($user['name']);
?>
<main class="max-w-4xl mx-auto px-4 py-6 space-y-8">
  <div>
    <h1 class="text-2xl font-bold text-gray-900">🛡️ Admin</h1>
    <p class="text-gray-500 text-sm mt-0.5">Konton, familjer och inloggningar</p>
  </div>

  <!-- Översikt -->
  <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
    <?php
    $cards = [
        ['👤', (int)$stats['users'],        'Konton'],
        ['👶', (int)$stats['children'],     'Barnprofiler'],
        ['🔗', (int)$stats['memberships'],  'Medlemskap'],
        ['🧾', (int)$stats['summaries'],    'Veckosammanställningar'],
        ['🍪', (int)$stats['active_tokens'],'Aktiva "kom ihåg mig"'],
        ['🚫', (int)$stats['failed_7d'],    'Missade inlogg (7 dgr)'],
    ];
    foreach ($cards as [$icon, $num, $label]): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
      <div class="text-2xl"><?= $icon ?></div>
      <div class="text-2xl font-bold text-gray-900 mt-1"><?= $num ?></div>
      <div class="text-xs text-gray-500 mt-0.5"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Förslagslådan -->
  <?php if ($suggestions): ?>
  <section>
    <h2 class="font-bold text-gray-900 text-lg mb-3">💡 Förslagslådan</h2>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-50">
      <?php foreach ($suggestions as $s): ?>
      <div class="px-4 py-3 flex items-start gap-3 <?= $s['done'] ? 'opacity-50' : '' ?>">
        <form method="POST" class="flex-shrink-0 mt-0.5">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
          <input type="hidden" name="suggestion_done" value="<?= (int)$s['id'] ?>">
          <button type="submit" title="<?= $s['done'] ? 'Markera som ohanterad' : 'Markera som hanterad' ?>"
                  class="w-7 h-7 rounded-full border-2 flex items-center justify-center transition-colors <?= $s['done'] ? 'bg-green-500 border-green-500' : 'border-gray-300 hover:border-green-400' ?>">
            <svg class="w-4 h-4 text-white <?= $s['done'] ? '' : 'opacity-0' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
          </button>
        </form>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-gray-800 whitespace-pre-line <?= $s['done'] ? 'line-through' : '' ?>"><?= htmlspecialchars($s['message']) ?></p>
          <p class="text-xs text-gray-400 mt-1">
            <?= htmlspecialchars($s['user_name'] ?? 'Borttaget konto') ?>
            <?php if (!empty($s['user_email'])): ?>· <?= htmlspecialchars($s['user_email']) ?><?php endif; ?>
            · <?= fmtTs($s['created_at']) ?>
          </p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Konton -->
  <section>
    <h2 class="font-bold text-gray-900 text-lg mb-3">👤 Konton</h2>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="px-4 py-3">Namn</th>
            <th class="px-4 py-3">E-post</th>
            <th class="px-4 py-3">Roller</th>
            <th class="px-4 py-3">Skapad</th>
            <th class="px-4 py-3">Senast inloggad</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr class="border-b border-gray-50 last:border-0">
            <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap"><?= htmlspecialchars($u['name']) ?></td>
            <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($u['email']) ?></td>
            <td class="px-4 py-3 whitespace-nowrap">
              <?php if ($u['owner_of'])  : ?><span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">Ägare × <?= $u['owner_of'] ?></span><?php endif; ?>
              <?php if ($u['parent_of']) : ?><span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">Förälder × <?= $u['parent_of'] ?></span><?php endif; ?>
              <?php if ($u['child_of'])  : ?><span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Barn × <?= $u['child_of'] ?></span><?php endif; ?>
              <?php if (!$u['owner_of'] && !$u['parent_of'] && !$u['child_of']): ?><span class="text-xs text-gray-400">–</span><?php endif; ?>
            </td>
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap"><?= fmtTs($u['created_at']) ?></td>
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap"><?= fmtTs($u['last_login']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Familjer -->
  <section>
    <h2 class="font-bold text-gray-900 text-lg mb-3">👨‍👩‍👧 Familjer</h2>
    <div class="space-y-3">
      <?php foreach ($families as $fam): ?>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
          <span class="font-bold text-gray-900">👨‍👩‍👧 <?= htmlspecialchars($fam['label']) ?></span>
          <span class="text-xs text-gray-400"><?= count($fam['children']) ?> barn · <?= count($fam['members']) ?> medlemmar</span>
        </div>

        <div class="mt-3 flex flex-wrap gap-1.5">
          <?php foreach ($fam['children'] as $c): ?>
          <span class="text-xs px-2 py-1 rounded-full text-white font-semibold" style="background-color:<?= htmlspecialchars($c['avatar_color'] ?: '#6366f1') ?>">
            👶 <?= htmlspecialchars($c['name']) ?> · <?= number_format((float)$c['weekly_amount'], 0, ',', ' ') ?> kr/v
          </span>
          <?php endforeach; ?>
        </div>

        <div class="mt-3 space-y-1.5">
          <?php foreach ($fam['members'] as $m): ?>
          <div class="text-sm flex items-center flex-wrap gap-1.5">
            <span class="font-medium text-gray-800"><?= htmlspecialchars($m['name']) ?></span>
            <span class="text-xs text-gray-400"><?= htmlspecialchars($m['email']) ?></span>
            <?php foreach ($m['roles'] as $r):
              [$roleLabel, $roleClass] = ROLE_LABELS[$r['role']] ?? [$r['role'], 'bg-gray-100 text-gray-700']; ?>
            <span class="text-xs px-2 py-0.5 rounded-full <?= $roleClass ?>"><?= $roleLabel ?> · <?= htmlspecialchars($r['child']) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($families)): ?>
      <p class="text-gray-400 text-sm">Inga barnprofiler ännu.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- Väntande inbjudningar -->
  <?php if ($invites): ?>
  <section>
    <h2 class="font-bold text-gray-900 text-lg mb-3">✉️ Väntande inbjudningar</h2>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-50">
      <?php foreach ($invites as $i): ?>
      <div class="px-4 py-3 text-sm flex items-center justify-between flex-wrap gap-1">
        <span class="text-gray-700">
          <strong><?= htmlspecialchars($i['invited_by_name']) ?></strong> bjöd in en
          <?= $i['role'] === 'child' ? 'barnanvändare' : 'förälder' ?> till
          <strong><?= htmlspecialchars($i['child_name']) ?></strong>
        </span>
        <span class="text-xs text-gray-400">skickad <?= fmtTs($i['created_at']) ?> · giltig till <?= fmtTs($i['expires_at']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Misslyckade inloggningar -->
  <?php if ($failedTop): ?>
  <section>
    <h2 class="font-bold text-gray-900 text-lg mb-3">🚫 Flest missade inlogg (7 dagar)</h2>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-50">
      <?php foreach ($failedTop as $f): ?>
      <div class="px-4 py-3 text-sm flex items-center justify-between flex-wrap gap-1">
        <span class="font-medium text-gray-800"><?= htmlspecialchars($f['email']) ?></span>
        <span class="text-gray-500"><?= (int)$f['n'] ?> försök · senast <?= fmtTs($f['last_try']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Senaste inloggningsförsök -->
  <section>
    <h2 class="font-bold text-gray-900 text-lg mb-3">🔑 Senaste inloggningsförsök</h2>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="px-4 py-3">Tid</th>
            <th class="px-4 py-3">E-post</th>
            <th class="px-4 py-3">IP</th>
            <th class="px-4 py-3">Resultat</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attempts as $a): ?>
          <tr class="border-b border-gray-50 last:border-0">
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap"><?= fmtTs($a['created_at']) ?></td>
            <td class="px-4 py-3 text-gray-700 whitespace-nowrap"><?= htmlspecialchars($a['email']) ?></td>
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap"><?= htmlspecialchars($a['ip'] ?? '–') ?></td>
            <td class="px-4 py-3 whitespace-nowrap">
              <?php if ($a['success']): ?>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-800">OK</span>
              <?php else: ?>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-800">Misslyckad</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($attempts)): ?>
          <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Inga inloggningsförsök loggade ännu — loggen börjar fyllas nu.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php pageFoot(); ?>
