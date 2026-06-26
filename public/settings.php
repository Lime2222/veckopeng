<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/layout.php';

$user  = requireAuth();
$id    = (int)($_GET['id'] ?? 0);
$child = requireChildOwnership($id, $user['id']);

$members            = getChildMembers($child['id']);
$allReqs            = getRequirementsWithExclusions($child['id']);
$childAccountMember = null;
foreach ($members as $m) {
    if ($m['role'] === 'child') { $childAccountMember = $m; break; }
}
$childInviteToken = $_SESSION['flash_child_invite_token'] ?? ''; unset($_SESSION['flash_child_invite_token']);
$pendingInvites = getPendingInvitations($child['id']);
$isOwner       = $child['role'] === 'owner';

$error       = $_SESSION['flash_error']        ?? ''; unset($_SESSION['flash_error']);
$success     = $_SESSION['flash_success']      ?? ''; unset($_SESSION['flash_success']);
$inviteToken = $_SESSION['flash_invite_token'] ?? ''; unset($_SESSION['flash_invite_token']);

pageHead('Inställningar – ' . $child['name']);
pageNav($user['name'], 0);
?>
<main class="max-w-lg mx-auto px-4 py-6">
  <div class="flex items-center gap-3 mb-6">
    <a href="/child.php?id=<?= $id ?>" class="p-2 rounded-xl text-gray-500 hover:bg-gray-100">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold text-lg"
         style="background-color:<?= htmlspecialchars($child['avatar_color']) ?>">
      <?= mb_substr($child['name'], 0, 1) ?>
    </div>
    <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($child['name']) ?> – Inställningar</h1>
  </div>

  <?php if ($error): ?>
  <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Child info -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-4">
    <h2 class="font-bold text-gray-900 mb-4">Grundinformation</h2>
    <form action="/api/update_child.php" method="POST" class="space-y-4">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
      <input type="hidden" name="child_id" value="<?= $id ?>">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Namn</label>
        <input type="text" name="name" value="<?= htmlspecialchars($child['name']) ?>" required
               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Swish-nummer (barnets)</label>
        <input type="tel" name="swish_number" value="<?= htmlspecialchars($child['swish_number'] ?? '') ?>"
               placeholder="t.ex. 0701234567"
               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base">
        <p class="text-xs text-gray-400 mt-1">Öppnar Swish med rätt belopp vid utbetalning</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Veckopeng (kr)</label>
        <input type="number" name="weekly_amount" value="<?= $child['weekly_amount'] ?>" min="0" step="0.5" required
               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Profilfärg</label>
        <div class="flex gap-2 flex-wrap">
          <?php foreach (['#6366f1','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#8b5cf6','#06b6d4'] as $c): ?>
          <label class="cursor-pointer">
            <input type="radio" name="avatar_color" value="<?= $c ?>" <?= $child['avatar_color'] === $c ? 'checked' : '' ?> class="sr-only">
            <span class="block w-9 h-9 rounded-full border-4 transition-all hover:scale-110"
                  style="background-color:<?= $c ?>;border-color:<?= $child['avatar_color'] === $c ? '#9ca3af' : 'transparent' ?>"
                  onclick="this.previousElementSibling.checked=true;document.querySelectorAll('[name=avatar_color]').forEach(r=>{r.nextElementSibling.style.borderColor=r.checked?'#9ca3af':'transparent'})"></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition-colors">Spara</button>
    </form>
  </div>

  <!-- Barnkonto -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">Barnkonto</h2>
      <p class="text-xs text-gray-400 mt-0.5">Låt <?= htmlspecialchars($child['name']) ?> logga in och se sina krav</p>
    </div>

    <!-- Self-report toggle -->
    <div class="px-5 py-4 border-b border-gray-50">
      <form action="/api/toggle_child_self_report.php" method="POST" class="flex items-center justify-between gap-4">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
        <input type="hidden" name="child_id" value="<?= $id ?>">
        <div>
          <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($child['name']) ?> kan fylla i krav själv</p>
          <p class="text-xs text-gray-400 mt-0.5">Annars kan barnet se men inte ändra något</p>
        </div>
        <button type="submit"
                class="flex-shrink-0 text-sm px-4 py-2 rounded-xl font-semibold transition-colors <?= $child['child_can_self_report'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
          <?= $child['child_can_self_report'] ? 'Ja' : 'Nej' ?>
        </button>
      </form>
    </div>

    <!-- Linked child account -->
    <?php if ($childAccountMember): ?>
    <div class="px-5 py-4 border-b border-gray-50 flex items-center gap-3">
      <div class="w-9 h-9 rounded-full bg-pink-100 flex items-center justify-center text-pink-700 font-bold text-sm flex-shrink-0">
        <?= mb_substr($childAccountMember['name'], 0, 1) ?>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($childAccountMember['name']) ?></p>
        <p class="text-xs text-gray-400">Barnkonto länkat</p>
      </div>
      <?php if ($isOwner): ?>
      <form action="/api/remove_parent.php" method="POST"
            onsubmit="return confirm('Ta bort barnkontot?')">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
        <input type="hidden" name="child_id" value="<?= $id ?>">
        <input type="hidden" name="user_id" value="<?= $childAccountMember['id'] ?>">
        <button type="submit" class="text-xs px-2.5 py-1.5 rounded-lg font-medium bg-red-50 text-red-600 hover:bg-red-100 transition-colors">Ta bort</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($childInviteToken): ?>
    <div class="mx-5 mb-4 mt-3 p-4 bg-green-50 border border-green-200 rounded-xl">
      <p class="text-sm font-semibold text-green-800 mb-2">✅ Inbjudningslänk skapad! Dela med <?= htmlspecialchars($child['name']) ?>:</p>
      <div class="flex gap-2">
        <input type="text" readonly
               value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/accept_invite.php?token=' . $childInviteToken) ?>"
               class="flex-1 px-3 py-2 bg-white border border-green-300 rounded-lg text-xs font-mono text-gray-700 min-w-0"
               onclick="this.select()">
        <button onclick="copyInviteLink(this.previousElementSibling,this)"
                class="flex-shrink-0 px-3 py-2 bg-green-600 text-white text-xs font-semibold rounded-lg hover:bg-green-700 transition-colors">
          Kopiera
        </button>
      </div>
      <p class="text-xs text-green-600 mt-2">Barnet skapar ett eget konto och får tillgång. Giltig i 7 dagar.</p>
    </div>
    <?php endif; ?>

    <?php if (!$childAccountMember && $isOwner): ?>
    <form action="/api/invite_child.php" method="POST" class="px-5 py-4">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
      <input type="hidden" name="child_id" value="<?= $id ?>">
      <button type="submit"
              class="w-full flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-dashed border-pink-200 text-pink-600 font-semibold hover:bg-pink-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Skapa inbjudningslänk till <?= htmlspecialchars($child['name']) ?>
      </button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Krav för detta barn -->
  <?php if (!empty($allReqs)): ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">Krav för <?= htmlspecialchars($child['name']) ?></h2>
      <p class="text-xs text-gray-400 mt-0.5">Välj vilka familjegemensamma krav som gäller för just detta barn</p>
    </div>
    <div class="divide-y divide-gray-50">
      <?php foreach ($allReqs as $req): $excluded = (bool)$req['excluded']; ?>
      <div class="flex items-center gap-3 px-5 py-3.5">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium <?= $excluded ? 'text-gray-400 line-through' : 'text-gray-800' ?>"><?= htmlspecialchars($req['name']) ?></p>
          <p class="text-xs text-gray-400 mt-0.5">
            <?= $req['type'] === 'minutes' ? $req['weekly_target_minutes'] . ' min/vecka' : ($req['frequency'] === 'weekly' ? 'Veckovis' : 'Daglig') ?>
          </p>
        </div>
        <form action="/api/toggle_child_requirement.php" method="POST">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
          <input type="hidden" name="child_id" value="<?= $id ?>">
          <input type="hidden" name="requirement_id" value="<?= $req['id'] ?>">
          <button type="submit" class="text-xs px-3 py-1.5 rounded-lg font-medium transition-colors <?= $excluded ? 'bg-gray-100 text-gray-400 hover:bg-indigo-50 hover:text-indigo-600' : 'bg-green-50 text-green-700 hover:bg-red-50 hover:text-red-600' ?>">
            <?= $excluded ? 'Undantagen' : 'Aktiv' ?>
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="px-5 py-3 border-t border-gray-50">
      <a href="/family.php" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Hantera vilka krav som finns → Familjeinställningar</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Föräldrar med tillgång -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">Föräldrar med tillgång</h2>
      <p class="text-xs text-gray-400 mt-0.5">Alla dessa ser och kan redigera <?= htmlspecialchars($child['name']) ?>s veckopeng</p>
    </div>

    <div class="divide-y divide-gray-50">
      <?php foreach ($members as $m): ?>
      <div class="flex items-center gap-3 px-5 py-3.5">
        <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm flex-shrink-0">
          <?= mb_substr($m['name'], 0, 1) ?>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($m['name']) ?></p>
          <p class="text-xs text-gray-400"><?= htmlspecialchars($m['email']) ?></p>
        </div>
        <?php if ($m['role'] === 'owner'): ?>
          <span class="text-xs font-semibold px-2 py-1 rounded-full bg-indigo-100 text-indigo-700">Ägare</span>
        <?php elseif ($isOwner): ?>
          <form action="/api/remove_parent.php" method="POST"
                onsubmit="return confirm('Ta bort <?= addslashes(htmlspecialchars($m['name'])) ?>s tillgång?')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
            <input type="hidden" name="child_id" value="<?= $id ?>">
            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
            <button type="submit" class="text-xs px-2.5 py-1.5 rounded-lg font-medium bg-red-50 text-red-600 hover:bg-red-100 transition-colors">Ta bort</button>
          </form>
        <?php else: ?>
          <span class="text-xs font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-500">Förälder</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($inviteToken): ?>
    <div class="mx-5 mb-4 mt-3 p-4 bg-green-50 border border-green-200 rounded-xl">
      <p class="text-sm font-semibold text-green-800 mb-2">✅ Inbjudningslänk skapad! Dela den med den andra föräldern:</p>
      <div class="flex gap-2">
        <input type="text" readonly
               value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/accept_invite.php?token=' . $inviteToken) ?>"
               class="flex-1 px-3 py-2 bg-white border border-green-300 rounded-lg text-xs font-mono text-gray-700 min-w-0"
               onclick="this.select()">
        <button onclick="copyInviteLink(this.previousElementSibling,this)"
                class="flex-shrink-0 px-3 py-2 bg-green-600 text-white text-xs font-semibold rounded-lg hover:bg-green-700 transition-colors">
          Kopiera
        </button>
      </div>
      <p class="text-xs text-green-600 mt-2">Giltig i 7 dagar. Den andra föräldern behöver ett konto (eller skapar ett).</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($pendingInvites)): ?>
    <div class="px-5 pb-3">
      <p class="text-xs text-gray-400 font-medium mb-2">Väntande inbjudningar:</p>
      <?php foreach ($pendingInvites as $inv): ?>
      <div class="flex items-center justify-between text-xs text-gray-500 py-1">
        <span>Skapad av <?= htmlspecialchars($inv['invited_by_name']) ?> · Går ut <?= date('j M', strtotime($inv['expires_at'])) ?></span>
        <a href="/accept_invite.php?token=<?= htmlspecialchars($inv['token']) ?>"
           class="font-mono text-indigo-500 hover:underline truncate max-w-[120px]">länk</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($isOwner): ?>
    <form action="/api/invite_parent.php" method="POST" class="px-5 py-4 border-t border-gray-50">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
      <input type="hidden" name="child_id" value="<?= $id ?>">
      <button type="submit"
              class="w-full flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-dashed border-indigo-200 text-indigo-600 font-semibold hover:bg-indigo-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Generera inbjudningslänk
      </button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Danger zone -->
  <div class="bg-white rounded-2xl border border-red-100 shadow-sm p-5 mb-6" x-data="{ open: false }">
    <button @click="open=!open" class="w-full flex items-center justify-between text-left">
      <span class="font-bold text-red-700">Farozon</span>
      <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-red-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="open" x-cloak class="mt-4 pt-4 border-t border-red-100">
      <p class="text-sm text-gray-600 mb-3">Ta bort barnprofilen och all data permanent.</p>
      <form action="/api/delete_child.php" method="POST" onsubmit="return confirm('Är du säker? All data för <?= addslashes(htmlspecialchars($child['name'])) ?> raderas permanent.')">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
        <input type="hidden" name="child_id" value="<?= $id ?>">
        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-xl transition-colors">
          Ta bort <?= htmlspecialchars($child['name']) ?> permanent
        </button>
      </form>
    </div>
  </div>
</main>
<script>
function copyInviteLink(input, btn) {
  var text = input.value;
  var done = function() { btn.textContent = '✓ Kopierad!'; setTimeout(function(){ btn.textContent = 'Kopiera'; }, 2000); };
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(done).catch(function() { fallbackCopy(input, done); });
  } else {
    fallbackCopy(input, done);
  }
}
function fallbackCopy(input, done) {
  input.select();
  input.setSelectionRange(0, 99999);
  try { document.execCommand('copy'); done(); } catch(e) {}
}
</script>
<?php pageFoot(); ?>
