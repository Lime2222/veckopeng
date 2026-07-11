<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/layout.php';

$user     = requireAuth();
$children = getChildren($user['id']);

// Child accounts go straight to their child page
foreach ($children as $c) {
    if ($c['role'] === 'child') {
        header('Location: /child.php?id=' . $c['id']);
        exit;
    }
}

$ws = weekStart();

$error   = $_SESSION['flash_error']   ?? ''; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);

pageHead('Startsida');
pageNav($user['name']);
?>
<main class="max-w-lg mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Hej, <?= htmlspecialchars($user['name']) ?>! 👋</h1>
      <p class="text-gray-500 text-sm mt-0.5">Vecka <?= date('W') ?> · <?= date('j M') ?></p>
    </div>
    <a href="/family.php" class="p-2 rounded-xl text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors" title="Familjeinställningar">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    </a>
  </div>

  <?php if ($error): ?>
  <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if (empty($children)): ?>
  <div class="bg-white rounded-2xl border border-dashed border-gray-200 p-10 text-center">
    <span class="text-5xl block mb-3">👶</span>
    <p class="text-gray-600 font-medium">Inga barn ännu</p>
    <p class="text-gray-400 text-sm mt-1">Lägg till ditt första barn nedan</p>
  </div>
  <?php else: ?>
  <div class="space-y-3">
    <?php foreach ($children as $child):
      $totals  = getWeekTotals($child['id'], $ws);
      $summary = getWeeklySummary($child['id'], $ws);
      $color   = htmlspecialchars($child['avatar_color']);
    ?>
    <a href="/child.php?id=<?= $child['id'] ?>"
       class="block bg-white rounded-2xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition-shadow active:scale-[0.99]">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-white text-2xl font-bold flex-shrink-0"
             style="background-color:<?= $color ?>">
          <?= mb_substr($child['name'], 0, 1) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between">
            <h2 class="font-bold text-gray-900 text-lg truncate"><?= htmlspecialchars($child['name']) ?></h2>
            <?php if ($summary): $sl = STATUS_LABELS[$summary['status']] ?? STATUS_LABELS['pending']; ?>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $sl['class'] ?>"><?= $sl['label'] ?></span>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-3 mt-1">
            <span class="text-sm text-gray-500">Veckopeng: <strong class="text-gray-700"><?= formatKr($child['weekly_amount']) ?></strong></span>
            <?php if ($totals['adjustments'] != 0): ?>
            <span class="text-sm <?= $totals['adjustments'] > 0 ? 'text-green-600' : 'text-red-500' ?>">
              <?= $totals['adjustments'] > 0 ? '+' : '' ?><?= formatKr($totals['adjustments']) ?>
            </span>
            <?php endif; ?>
          </div>
          <div class="mt-2 flex items-center gap-2">
            <div class="flex-1 bg-gray-100 rounded-full h-1.5">
              <?php $pct = $totals['req_total'] > 0 ? round(100 * $totals['req_done'] / $totals['req_total']) : 0; ?>
              <div class="h-1.5 rounded-full bg-indigo-500" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="text-xs text-gray-400"><?= $totals['req_done'] ?>/<?= $totals['req_total'] ?> krav</span>
          </div>
        </div>
        <svg class="w-5 h-5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Add child (visas bara tills första barnet finns - därefter i Familjeinställningar) -->
  <?php if (empty($children)): ?>
  <div x-data="{ open: false }" class="mt-6">
    <button @click="open=!open"
            class="w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl border-2 border-dashed border-indigo-200 text-indigo-600 font-semibold hover:bg-indigo-50 transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Lägg till barn
    </button>

    <div x-show="open" x-cloak class="mt-3 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <form action="/api/add_child.php" method="POST" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Barnets namn</label>
          <input type="text" name="name" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Veckopeng (kr)</label>
          <input type="number" name="weekly_amount" value="50" min="0" step="0.5" required
                 class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Profilfärg</label>
          <div class="flex gap-2 flex-wrap">
            <?php foreach (['#6366f1','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#8b5cf6','#06b6d4'] as $c): ?>
            <label class="cursor-pointer">
              <input type="radio" name="avatar_color" value="<?= $c ?>" class="sr-only" <?= $c === '#6366f1' ? 'checked' : '' ?>>
              <span class="block w-9 h-9 rounded-full border-4 border-transparent peer-checked:border-gray-400 hover:scale-110 transition-transform"
                    style="background-color:<?= $c ?>"
                    onclick="this.previousElementSibling.checked=true;document.querySelectorAll('[name=avatar_color]').forEach(r=>{r.nextElementSibling.style.borderColor=r.checked?'#9ca3af':'transparent'})"></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl text-base transition-colors">
          Lägg till
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Förslagslådan -->
  <div x-data="{ open: false }" class="mt-6 mb-4">
    <button @click="open=!open"
            class="w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl border-2 border-dashed border-amber-200 text-amber-600 font-semibold hover:bg-amber-50 transition-colors">
      <span class="text-lg">💡</span>
      Förslagslådan
    </button>

    <div x-show="open" x-cloak class="mt-3 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <h2 class="font-bold text-gray-900 mb-1">Har du en idé?</h2>
      <p class="text-sm text-gray-500 mb-4">
        Här kan du skicka in förbättringstips, önskemål om nya funktioner eller
        berätta om något som strular. Alla förslag läses — stort som smått!
      </p>
      <form action="/api/add_suggestion.php" method="POST" class="space-y-3">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
        <textarea name="message" rows="4" required maxlength="5000"
                  placeholder="Skriv ditt förslag här…"
                  class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-400 focus:border-transparent text-base resize-y"></textarea>
        <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold py-3 rounded-xl text-base transition-colors">
          Skicka in förslag
        </button>
      </form>
    </div>
  </div>
</main>
<?php pageFoot(); ?>
