<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/layout.php';

$user  = requireAuth();
$id    = (int)($_GET['id'] ?? 0);
$child = requireChildOwnership($id, $user['id']);

$requirements = getRequirements($child['id'], false);
$deductTypes  = getDeductionTypes($child['id'], false);

$error   = $_SESSION['flash_error']   ?? ''; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);

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

  <!-- Requirements -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
      <div>
        <h2 class="font-bold text-gray-900">Krav</h2>
        <p class="text-xs text-gray-400 mt-0.5">Saker barnet ska göra varje dag</p>
      </div>
    </div>
    <div class="divide-y divide-gray-50">
      <?php foreach ($requirements as $req): ?>
      <div class="flex items-center gap-2 px-5 py-3.5">
        <span class="flex-1 text-gray-800 text-sm <?= !$req['active'] ? 'line-through text-gray-400' : '' ?>"><?= htmlspecialchars($req['name']) ?></span>

        <!-- Frekvens: daglig / veckovis -->
        <form action="/api/set_requirement_frequency.php" method="POST" class="inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
          <input type="hidden" name="requirement_id" value="<?= $req['id'] ?>">
          <input type="hidden" name="child_id" value="<?= $id ?>">
          <input type="hidden" name="frequency" value="<?= $req['frequency'] === 'weekly' ? 'daily' : 'weekly' ?>">
          <button type="submit" title="<?= $req['frequency'] === 'weekly' ? 'Veckovis – klicka för daglig' : 'Daglig – klicka för veckovis' ?>"
                  class="text-xs px-2.5 py-1.5 rounded-lg font-medium transition-colors <?= $req['frequency'] === 'weekly' ? 'bg-purple-100 text-purple-700 hover:bg-purple-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
            <?= $req['frequency'] === 'weekly' ? '📅 Vecka' : '📆 Daglig' ?>
          </button>
        </form>

        <!-- Aktiv / Inaktiv -->
        <form action="/api/toggle_requirement.php" method="POST" class="inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
          <input type="hidden" name="requirement_id" value="<?= $req['id'] ?>">
          <input type="hidden" name="child_id" value="<?= $id ?>">
          <button type="submit"
                  class="text-xs px-2.5 py-1.5 rounded-lg font-medium transition-colors <?= $req['active'] ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
            <?= $req['active'] ? 'Aktiv' : 'Inaktiv' ?>
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <form action="/api/add_requirement.php" method="POST" class="px-5 py-4 border-t border-gray-50 flex gap-2">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
      <input type="hidden" name="child_id" value="<?= $id ?>">
      <input type="text" name="name" placeholder="Nytt krav, t.ex. Städa rum" required
             class="flex-1 px-3 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      <button type="submit" class="touch-btn px-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-lg transition-colors">+</button>
    </form>
  </div>

  <!-- Deduction types -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">Avdrag &amp; Bonusar</h2>
      <p class="text-xs text-gray-400 mt-0.5">Negativt belopp = avdrag, positivt = bonus</p>
    </div>
    <div class="divide-y divide-gray-50">
      <?php foreach ($deductTypes as $dt): ?>
      <div class="flex items-center gap-3 px-5 py-3.5">
        <span class="w-16 text-right font-bold text-sm flex-shrink-0 <?= (float)$dt['amount'] >= 0 ? 'text-green-600' : 'text-red-500' ?>">
          <?= (float)$dt['amount'] > 0 ? '+' : '' ?><?= formatKr((float)$dt['amount']) ?>
        </span>
        <span class="flex-1 text-gray-800 text-sm <?= !$dt['active'] ? 'line-through text-gray-400' : '' ?>"><?= htmlspecialchars($dt['name']) ?></span>
        <form action="/api/toggle_deduction_type.php" method="POST" class="inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
          <input type="hidden" name="deduction_type_id" value="<?= $dt['id'] ?>">
          <input type="hidden" name="child_id" value="<?= $id ?>">
          <button type="submit"
                  class="text-xs px-2.5 py-1.5 rounded-lg font-medium transition-colors <?= $dt['active'] ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
            <?= $dt['active'] ? 'Aktiv' : 'Inaktiv' ?>
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <form action="/api/add_deduction_type.php" method="POST" class="px-5 py-4 border-t border-gray-50 space-y-2">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
      <input type="hidden" name="child_id" value="<?= $id ?>">
      <div class="flex gap-2">
        <input type="number" name="amount" placeholder="-5 eller +10" step="0.5" required
               class="w-28 px-3 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <input type="text" name="name" placeholder="Ej dukat av tallrik" required
               class="flex-1 px-3 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <button type="submit" class="touch-btn px-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-lg transition-colors">+</button>
      </div>
    </form>
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
<?php pageFoot(); ?>
