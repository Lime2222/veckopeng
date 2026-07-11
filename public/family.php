<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/layout.php';

$user     = requireAuth();
$children = getChildren($user['id']);

// Pick first owned child as reference for API calls; fall back to any child
$refChild = null;
foreach ($children as $c) {
    if ($c['role'] === 'owner') { $refChild = $c; break; }
}
if (!$refChild && !empty($children)) $refChild = $children[0];

$requirements   = $refChild ? getRequirements($refChild['id'], false) : [];
$deductTypes    = $refChild ? getDeductionTypes($refChild['id'], false) : [];
$refId          = $refChild ? (int)$refChild['id'] : 0;
$paymentTotals  = getPaymentTotalsPerParent($user['id']);
$policyCfg      = $refChild ? getFamilyPolicy($refChild['id']) : ['policy' => 'none', 'penalty' => 0.0];

$error   = $_SESSION['flash_error']   ?? ''; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);

pageHead('Familjeinställningar');
pageNav($user['name'], 0);
?>
<main class="max-w-lg mx-auto px-4 py-6">
  <div class="flex items-center gap-3 mb-6">
    <a href="/dashboard.php" class="p-2 rounded-xl text-gray-500 hover:bg-gray-100">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <div>
      <h1 class="text-xl font-bold text-gray-900">Familjeinställningar</h1>
      <p class="text-xs text-gray-400 mt-0.5">Gäller alla barn i familjen</p>
    </div>
  </div>

  <?php if ($error): ?>
  <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Child order -->
  <?php if (count($children) > 1): ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">Ordning på barnen</h2>
      <p class="text-xs text-gray-400 mt-0.5">Ändrar ordningen i menyn och på startsidan</p>
    </div>
    <div class="divide-y divide-gray-50">
      <?php foreach ($children as $i => $c):
        $isFirst = $i === 0;
        $isLast  = $i === count($children) - 1;
      ?>
      <div class="px-5 py-3 flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
             style="background-color:<?= htmlspecialchars($c['avatar_color']) ?>">
          <?= mb_substr($c['name'], 0, 1) ?>
        </div>
        <span class="flex-1 text-gray-800 text-sm font-medium"><?= htmlspecialchars($c['name']) ?></span>
        <div class="flex gap-1">
          <form method="POST" action="/api/reorder_child.php">
            <input type="hidden" name="csrf"      value="<?= htmlspecialchars(csrf()) ?>">
            <input type="hidden" name="child_id"  value="<?= $c['id'] ?>">
            <input type="hidden" name="direction" value="up">
            <input type="hidden" name="redirect"  value="/family.php">
            <button type="submit" <?= $isFirst ? 'disabled' : '' ?>
                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors <?= $isFirst ? 'text-gray-200 cursor-default' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-100' ?>">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
            </button>
          </form>
          <form method="POST" action="/api/reorder_child.php">
            <input type="hidden" name="csrf"      value="<?= htmlspecialchars(csrf()) ?>">
            <input type="hidden" name="child_id"  value="<?= $c['id'] ?>">
            <input type="hidden" name="direction" value="down">
            <input type="hidden" name="redirect"  value="/family.php">
            <button type="submit" <?= $isLast ? 'disabled' : '' ?>
                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors <?= $isLast ? 'text-gray-200 cursor-default' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-100' ?>">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$refChild): ?>
  <div class="bg-white rounded-2xl border border-dashed border-gray-200 p-10 text-center">
    <span class="text-5xl block mb-3">👶</span>
    <p class="text-gray-600 font-medium">Inga barn ännu</p>
    <p class="text-gray-400 text-sm mt-1">Lägg till ditt första barn nedan</p>
  </div>
  <?php else: ?>

  <!-- Payment totals per parent -->
  <?php if (!empty($paymentTotals)): ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">Betalningslogg</h2>
      <p class="text-xs text-gray-400 mt-0.5">Totalt utbetalt veckopeng per person</p>
    </div>
    <div class="divide-y divide-gray-50">
      <?php
        $grandTotal = array_sum(array_column($paymentTotals, 'total'));
      ?>
      <?php foreach ($paymentTotals as $row): ?>
      <?php $pct = $grandTotal > 0 ? round(100 * $row['total'] / $grandTotal) : 0; ?>
      <div class="px-5 py-3">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($row['name']) ?></span>
          <span class="text-sm font-bold text-gray-900"><?= formatKr($row['total']) ?></span>
        </div>
        <div class="flex items-center gap-2">
          <div class="flex-1 bg-gray-100 rounded-full h-1.5">
            <div class="h-1.5 rounded-full bg-indigo-500" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="text-xs text-gray-400 w-8 text-right"><?= $pct ?>%</span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (count($paymentTotals) > 1): ?>
      <div class="px-5 py-3 flex justify-between">
        <span class="text-xs text-gray-400">Totalt</span>
        <span class="text-xs font-semibold text-gray-600"><?= formatKr($grandTotal) ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Requirements -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">Krav</h2>
      <p class="text-xs text-gray-400 mt-0.5">Saker barnen ska göra – gäller alla barn i familjen</p>
    </div>
    <div class="divide-y divide-gray-50">
      <?php foreach ($requirements as $req): ?>
      <div x-data="{ editing: false }" class="px-5 py-3">

        <!-- Visningsläge -->
        <div x-show="!editing" class="flex items-center gap-2">
          <div class="flex-1 min-w-0">
            <span class="text-gray-800 text-sm <?= !$req['active'] ? 'line-through text-gray-400' : '' ?>"><?= htmlspecialchars($req['name']) ?></span>
            <?php if ($req['type'] === 'minutes'): ?>
              <span class="ml-1.5 text-xs text-indigo-500 font-medium"><?= $req['weekly_target_minutes'] ?> min/v</span>
            <?php endif; ?>
            <span class="ml-1.5 text-xs text-gray-400"><?= $req['frequency'] === 'weekly' ? '· Vecka' : '' ?></span>
          </div>
          <button @click="editing=true" class="p-1.5 text-gray-400 hover:text-indigo-600 rounded-lg hover:bg-indigo-50 transition-colors" title="Redigera">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </button>
          <form action="/api/toggle_requirement.php" method="POST" class="inline">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
            <input type="hidden" name="requirement_id" value="<?= $req['id'] ?>">
            <input type="hidden" name="child_id" value="<?= $refId ?>">
            <input type="hidden" name="redirect" value="/family.php">
            <button type="submit" class="text-xs px-2 py-1.5 rounded-lg font-medium transition-colors <?= $req['active'] ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' ?>">
              <?= $req['active'] ? 'Aktiv' : 'Inaktiv' ?>
            </button>
          </form>
          <form action="/api/delete_requirement.php" method="POST" class="inline"
                onsubmit="return confirm('Ta bort kravet permanent?')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
            <input type="hidden" name="requirement_id" value="<?= $req['id'] ?>">
            <input type="hidden" name="child_id" value="<?= $refId ?>">
            <input type="hidden" name="redirect" value="/family.php">
            <button type="submit" class="p-1.5 text-gray-300 hover:text-red-500 rounded-lg hover:bg-red-50 transition-colors" title="Ta bort">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </form>
        </div>

        <!-- Redigeringsläge -->
        <form x-show="editing" x-cloak action="/api/update_requirement.php" method="POST" class="space-y-2">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
          <input type="hidden" name="requirement_id" value="<?= $req['id'] ?>">
          <input type="hidden" name="child_id" value="<?= $refId ?>">
          <input type="hidden" name="redirect" value="/family.php">
          <div class="flex gap-2">
            <input type="text" name="name" value="<?= htmlspecialchars($req['name']) ?>" required
                   class="flex-1 px-3 py-2 border border-indigo-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold transition-colors">Spara</button>
            <button type="button" @click="editing=false" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl text-sm font-semibold transition-colors">Avbryt</button>
          </div>
          <div class="flex gap-2">
            <form action="/api/set_requirement_frequency.php" method="POST" class="inline">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
              <input type="hidden" name="requirement_id" value="<?= $req['id'] ?>">
              <input type="hidden" name="child_id" value="<?= $refId ?>">
              <input type="hidden" name="redirect" value="/family.php">
              <input type="hidden" name="frequency" value="<?= $req['frequency'] === 'weekly' ? 'daily' : 'weekly' ?>">
              <button type="submit" class="text-xs px-2.5 py-1.5 rounded-lg font-medium transition-colors <?= $req['frequency'] === 'weekly' ? 'bg-purple-100 text-purple-700 hover:bg-purple-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
                <?= $req['frequency'] === 'weekly' ? '📅 Veckovis' : '📆 Daglig' ?> – byt
              </button>
            </form>
          </div>
        </form>

      </div>
      <?php endforeach; ?>
    </div>
    <form action="/api/add_requirement.php" method="POST" class="px-5 py-4 border-t border-gray-50 space-y-2"
          x-data="{ rtype: 'checkbox' }">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
      <input type="hidden" name="child_id" value="<?= $refId ?>">
      <input type="hidden" name="redirect" value="/family.php">
      <div class="flex gap-2">
        <input type="text" name="name" placeholder="Nytt krav, t.ex. Städa rum" required
               class="flex-1 px-3 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <select name="type" x-model="rtype"
                class="px-3 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white">
          <option value="checkbox">Kryssruta</option>
          <option value="minutes">Minuter/vecka</option>
        </select>
        <button type="submit" class="touch-btn px-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-lg transition-colors">+</button>
      </div>
      <div x-show="rtype === 'minutes'" x-cloak class="flex items-center gap-2">
        <input type="number" name="weekly_target_minutes" min="1" placeholder="Veckamål i minuter, t.ex. 120"
               class="flex-1 px-3 py-3 border border-indigo-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <span class="text-xs text-gray-400 whitespace-nowrap">min/vecka</span>
      </div>
    </form>
  </div>

  <!-- Veckopengsregler -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4" x-data="{ policy: '<?= htmlspecialchars($policyCfg['policy']) ?>' }">
    <div class="px-5 py-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">⚖️ Veckopengsregler</h2>
      <p class="text-xs text-gray-400 mt-0.5">Vad händer med bas-veckopengen när krav missas?</p>
    </div>
    <form action="/api/set_family_policy.php" method="POST" class="p-5 space-y-2">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
      <input type="hidden" name="child_id" value="<?= $refId ?>">

      <?php
      $policyOptions = [
          'none'    => ['😊 Basen betalas alltid ut',        'Missade krav påverkar inte veckopengen'],
          'fixed'   => ['💸 Fast avdrag per missat krav',    'Ett bestämt belopp dras av för varje miss'],
          'percent' => ['📉 Procentavdrag per missat krav',  'En andel av basen dras av för varje miss'],
          'all'     => ['🔒 Alla krav krävs',                'Ett enda missat krav ger 0 kr i bas'],
      ];
      foreach ($policyOptions as $val => [$label, $desc]): ?>
      <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
             :class="policy === '<?= $val ?>' ? 'border-indigo-300 bg-indigo-50' : 'border-gray-100 hover:border-gray-200'">
        <input type="radio" name="policy" value="<?= $val ?>" x-model="policy" class="mt-1 accent-indigo-600">
        <span>
          <span class="block text-sm font-semibold text-gray-800"><?= $label ?></span>
          <span class="block text-xs text-gray-400 mt-0.5"><?= $desc ?></span>
        </span>
      </label>
      <?php endforeach; ?>

      <div x-show="policy === 'fixed' || policy === 'percent'" x-cloak class="flex items-center gap-2 pt-1">
        <span class="text-sm text-gray-600">Avdrag per missat krav:</span>
        <input type="number" name="penalty" value="<?= $policyCfg['penalty'] > 0 ? htmlspecialchars(rtrim(rtrim(number_format($policyCfg['penalty'], 2, '.', ''), '0'), '.')) : '' ?>"
               min="0" step="0.5" placeholder="t.ex. 5"
               class="w-24 px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <span class="text-sm text-gray-500" x-text="policy === 'percent' ? '% av basen' : 'kr'"></span>
      </div>

      <p class="text-xs text-gray-400 pt-1">
        Ett "missat krav" = en passerad dag utan bock för dagliga krav, eller ett vecko-/minutkrav
        som inte klarats när veckan är slut. Avdraget syns direkt på barnets sida och kan aldrig
        bli mer än basen. Bonusar och avdrag från knapparna påverkas inte.
      </p>

      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition-colors">Spara regler</button>
    </form>
  </div>

  <!-- Deduction types -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-50">
      <h2 class="font-bold text-gray-900">Avdrag &amp; Bonusar</h2>
      <p class="text-xs text-gray-400 mt-0.5">Negativt belopp = avdrag, positivt = bonus – gäller alla barn</p>
    </div>
    <div class="divide-y divide-gray-50">
      <?php foreach ($deductTypes as $dt): $amt = (float)$dt['amount']; ?>
      <div x-data="{ editing: false }" class="px-5 py-3">

        <!-- Visningsläge -->
        <div x-show="!editing" class="flex items-center gap-2">
          <span class="w-14 text-right font-bold text-sm flex-shrink-0 <?= $amt >= 0 ? 'text-green-600' : 'text-red-500' ?>">
            <?= $amt > 0 ? '+' : '' ?><?= formatKr($amt) ?>
          </span>
          <span class="flex-1 text-gray-800 text-sm truncate <?= !$dt['active'] ? 'line-through text-gray-400' : '' ?>"><?= htmlspecialchars($dt['name']) ?></span>
          <button @click="editing=true" class="p-1.5 text-gray-400 hover:text-indigo-600 rounded-lg hover:bg-indigo-50 transition-colors" title="Redigera">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </button>
          <form action="/api/toggle_deduction_type.php" method="POST" class="inline">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
            <input type="hidden" name="deduction_type_id" value="<?= $dt['id'] ?>">
            <input type="hidden" name="child_id" value="<?= $refId ?>">
            <input type="hidden" name="redirect" value="/family.php">
            <button type="submit" class="text-xs px-2 py-1.5 rounded-lg font-medium transition-colors <?= $dt['active'] ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' ?>">
              <?= $dt['active'] ? 'Aktiv' : 'Inaktiv' ?>
            </button>
          </form>
          <form action="/api/delete_deduction_type.php" method="POST" class="inline"
                onsubmit="return confirm('Ta bort permanent?')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
            <input type="hidden" name="deduction_type_id" value="<?= $dt['id'] ?>">
            <input type="hidden" name="child_id" value="<?= $refId ?>">
            <input type="hidden" name="redirect" value="/family.php">
            <button type="submit" class="p-1.5 text-gray-300 hover:text-red-500 rounded-lg hover:bg-red-50 transition-colors" title="Ta bort">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </form>
        </div>

        <!-- Redigeringsläge -->
        <form x-show="editing" x-cloak action="/api/update_deduction_type.php" method="POST">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
          <input type="hidden" name="deduction_type_id" value="<?= $dt['id'] ?>">
          <input type="hidden" name="child_id" value="<?= $refId ?>">
          <input type="hidden" name="redirect" value="/family.php">
          <div class="flex gap-2">
            <input type="number" name="amount" value="<?= $amt ?>" step="0.5" required
                   class="w-24 px-3 py-2 border border-indigo-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <input type="text" name="name" value="<?= htmlspecialchars($dt['name']) ?>" required
                   class="flex-1 px-3 py-2 border border-indigo-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold transition-colors">Spara</button>
            <button type="button" @click="editing=false" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl text-sm font-semibold transition-colors">Avbryt</button>
          </div>
        </form>

      </div>
      <?php endforeach; ?>
    </div>
    <form action="/api/add_deduction_type.php" method="POST" class="px-5 py-4 border-t border-gray-50 space-y-2">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
      <input type="hidden" name="child_id" value="<?= $refId ?>">
      <input type="hidden" name="redirect" value="/family.php">
      <div class="flex gap-2">
        <input type="number" name="amount" placeholder="-5 eller +10" step="0.5" required
               class="w-28 px-3 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <input type="text" name="name" placeholder="Ej dukat av tallrik" required
               class="flex-1 px-3 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <button type="submit" class="touch-btn px-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-lg transition-colors">+</button>
      </div>
    </form>
  </div>

  <?php endif; ?>

  <!-- Add child (flyttad hit från startsidan - behövs sällan efter uppstarten) -->
  <div x-data="{ open: false }" class="mb-6">
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
              <span class="block w-9 h-9 rounded-full border-4 border-transparent hover:scale-110 transition-transform"
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
</main>
<?php pageFoot(); ?>
