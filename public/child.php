<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/layout.php';

$user  = requireAuth();
$id    = (int)($_GET['id'] ?? 0);
$child = requireChildOwnership($id, $user['id']);

$ws      = weekStart($_GET['week'] ?? null);
$dates   = weekDates($ws);
$we      = end($dates); // Sunday
$today   = date('Y-m-d');
$selDate = $_GET['date'] ?? (in_array($today, $dates) ? $today : $today);
if (!in_array($selDate, $dates)) $selDate = $dates[0];

$totals       = getWeekTotals($child['id'], $ws);
$requirements = getRequirements($child['id']);
$deductTypes  = getDeductionTypes($child['id']);
$dayLogs      = getDayLogs($child['id'], $selDate);
$weekAdj      = getWeekAdjustments($child['id'], $ws);
$summary      = getWeeklySummary($child['id'], $ws);

$prevWeek = (new DateTime($ws))->modify('-7 days')->format('Y-m-d');
$nextWeek = (new DateTime($ws))->modify('+7 days')->format('Y-m-d');
$isCurrentWeek = $ws === weekStart();

pageHead($child['name']);
pageNav($user['name'], $child['id']);
?>
<main class="max-w-lg mx-auto px-4 py-4" x-data="weekView()" x-init="init()">

  <!-- Week navigation -->
  <div class="flex items-center justify-between mb-4">
    <a href="?id=<?= $id ?>&week=<?= $prevWeek ?>" class="p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <div class="text-center">
      <h1 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($child['name']) ?></h1>
      <p class="text-sm text-gray-500">
        <?= date('j', strtotime($ws)) ?>–<?= date('j M', strtotime($we)) ?> · Vecka <?= date('W', strtotime($ws)) ?>
      </p>
    </div>
    <?php if (!$isCurrentWeek): ?>
    <a href="?id=<?= $id ?>&week=<?= $nextWeek ?>" class="p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php else: ?>
    <div class="w-9"></div>
    <?php endif; ?>
  </div>

  <!-- Week total card -->
  <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-4 mb-4 text-white">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-indigo-200 text-sm">Beräknad veckopeng</p>
        <p class="text-3xl font-bold mt-0.5" id="final-amount"><?= formatKr($totals['final']) ?></p>
      </div>
      <div class="text-right">
        <p class="text-indigo-200 text-xs">Bas: <?= formatKr($totals['base']) ?></p>
        <?php if ($totals['adjustments'] != 0): ?>
        <p class="text-xs <?= $totals['adjustments'] > 0 ? 'text-green-300' : 'text-red-300' ?>">
          <?= $totals['adjustments'] > 0 ? '+' : '' ?><?= formatKr($totals['adjustments']) ?>
        </p>
        <?php endif; ?>
        <p class="text-indigo-200 text-xs mt-1"><?= $totals['req_done'] ?>/<?= $totals['req_total'] ?> krav ✓</p>
      </div>
    </div>
    <?php if ($summary): $sl = STATUS_LABELS[$summary['status']] ?? STATUS_LABELS['pending']; ?>
    <div class="mt-3 pt-3 border-t border-indigo-500 flex items-center justify-between">
      <span class="text-sm text-indigo-200">Sammanställd</span>
      <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white/20 text-white"><?= $sl['label'] ?></span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Day selector -->
  <div class="flex gap-1.5 mb-4 overflow-x-auto pb-1 scrollbar-hide">
    <?php foreach ($dates as $i => $d):
      $isToday = $d === $today;
      $isSel   = $d === $selDate;
      $hasLogs = false;
      if (!$isSel) {
        $logs = getDayLogs($child['id'], $d);
        $hasLogs = count(array_filter($logs, fn($l) => $l['completed'])) > 0;
      }
    ?>
    <a href="?id=<?= $id ?>&week=<?= $ws ?>&date=<?= $d ?>"
       class="flex-shrink-0 flex flex-col items-center py-2 px-3 rounded-xl font-medium text-sm transition-all <?= $isSel ? 'bg-indigo-600 text-white shadow-md' : ($isToday ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-white text-gray-600 border border-gray-100') ?>">
      <span class="text-xs <?= $isSel ? 'text-indigo-200' : 'text-gray-400' ?>"><?= SHORT_DAYS[$i] ?></span>
      <span><?= date('j', strtotime($d)) ?></span>
      <?php if ($hasLogs && !$isSel): ?><span class="w-1 h-1 rounded-full bg-green-400 mt-0.5"></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Day requirements -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
      <h2 class="font-semibold text-gray-900">
        <?= SWEDISH_DAYS[(int)date('N', strtotime($selDate)) - 1] ?> <?= date('j', strtotime($selDate)) ?>
      </h2>
      <?php if (empty($requirements)): ?>
      <a href="/settings.php?id=<?= $id ?>" class="text-xs text-indigo-600 font-medium">+ Lägg till krav</a>
      <?php endif; ?>
    </div>

    <?php if (empty($requirements)): ?>
    <div class="px-4 py-6 text-center text-gray-400 text-sm">
      Inga krav inlagda än. <a href="/settings.php?id=<?= $id ?>" class="text-indigo-600 font-medium">Lägg till krav →</a>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-50" id="req-list">
      <?php foreach ($dayLogs as $log): ?>

      <?php if ($log['type'] === 'minutes'):
        $target  = (int)($log['weekly_target_minutes'] ?? 0);
        $weekMin = (int)$log['minutes_week'];
        $pct     = $target > 0 ? min(100, round(100 * $weekMin / $target)) : 0;
        $done    = $target > 0 && $weekMin >= $target;
      ?>
      <div class="px-4 py-4" id="min-row-<?= $log['id'] ?>">
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full border-2 flex items-center justify-center flex-shrink-0 <?= $done ? 'bg-green-500 border-green-500' : 'border-gray-300' ?>">
              <svg class="w-4 h-4 text-white <?= $done ? '' : 'opacity-0' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            </div>
            <span class="font-medium text-gray-800"><?= htmlspecialchars($log['name']) ?></span>
          </div>
          <span class="text-sm font-semibold <?= $done ? 'text-green-600' : 'text-gray-500' ?>" id="min-week-<?= $log['id'] ?>"><?= $weekMin ?>/<?= $target ?> min</span>
        </div>
        <div class="bg-gray-100 rounded-full h-2 mb-3">
          <div class="h-2 rounded-full transition-all <?= $done ? 'bg-green-500' : 'bg-indigo-500' ?>"
               id="min-bar-<?= $log['id'] ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="text-xs text-gray-400 mr-1">Idag:</span>
          <?php foreach ([10, 20, 30, 60] as $m): ?>
          <button onclick="addMinutes(<?= $child['id'] ?>, <?= $log['id'] ?>, '<?= $selDate ?>', <?= $m ?>, <?= $target ?>)"
                  class="flex-1 py-2.5 rounded-xl bg-indigo-50 text-indigo-700 text-sm font-bold hover:bg-indigo-100 active:scale-95 transition-all">
            +<?= $m ?>
          </button>
          <?php endforeach; ?>
          <span class="text-sm font-bold text-gray-700 px-2 min-w-[52px] text-center" id="min-today-<?= $log['id'] ?>"><?= (int)$log['minutes_today'] ?> min</span>
          <button onclick="addMinutes(<?= $child['id'] ?>, <?= $log['id'] ?>, '<?= $selDate ?>', -10, <?= $target ?>)"
                  class="px-3 py-2.5 rounded-xl bg-red-50 text-red-500 text-sm font-bold hover:bg-red-100 active:scale-95 transition-all">-10</button>
        </div>
      </div>

      <?php else: ?>
      <label class="flex items-center gap-4 px-4 py-4 cursor-pointer hover:bg-gray-50 active:bg-gray-100 transition-colors req-row"
             data-req-id="<?= $log['id'] ?>"
             data-date="<?= $selDate ?>"
             data-child-id="<?= $child['id'] ?>">
        <div class="relative flex-shrink-0">
          <input type="checkbox" class="sr-only req-checkbox" <?= $log['completed'] ? 'checked' : '' ?>>
          <div class="w-7 h-7 rounded-full border-2 flex items-center justify-center transition-all req-check-box <?= $log['completed'] ? 'bg-green-500 border-green-500' : 'border-gray-300' ?>">
            <svg class="w-4 h-4 text-white <?= $log['completed'] ? '' : 'opacity-0' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <span class="text-gray-800 font-medium req-label <?= $log['completed'] ? 'line-through text-gray-400' : '' ?>"><?= htmlspecialchars($log['name']) ?></span>
          <?php if ($log['frequency'] === 'weekly'): ?>
          <span class="ml-1.5 text-xs font-medium px-1.5 py-0.5 rounded bg-purple-100 text-purple-600">Vecka</span>
          <?php endif; ?>
        </div>
      </label>
      <?php endif; ?>

      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Quick adjustments -->
  <?php if (!empty($deductTypes)): ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-4 py-3 border-b border-gray-50">
      <h2 class="font-semibold text-gray-900">Snabbknappar</h2>
      <p class="text-xs text-gray-400 mt-0.5">Avdrag &amp; bonus för <?= date('j/n', strtotime($selDate)) ?></p>
    </div>
    <div class="p-4 grid grid-cols-2 gap-2">
      <?php foreach ($deductTypes as $dt): ?>
      <button
        onclick="addAdjustment(<?= $child['id'] ?>, <?= $dt['id'] ?>, '<?= addslashes($selDate) ?>', '<?= htmlspecialchars(addslashes($dt['name'])) ?>', <?= $dt['amount'] ?>)"
        class="touch-btn flex flex-col items-center justify-center rounded-xl px-3 py-3 font-semibold text-sm transition-colors border <?= $dt['amount'] < 0 ? 'bg-red-50 border-red-100 text-red-700 hover:bg-red-100' : 'bg-green-50 border-green-100 text-green-700 hover:bg-green-100' ?>">
        <span class="text-base font-bold"><?= $dt['amount'] > 0 ? '+' : '' ?><?= formatKr((float)$dt['amount']) ?></span>
        <span class="text-xs mt-0.5 text-center leading-tight"><?= htmlspecialchars($dt['name']) ?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Custom adjustment -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4" x-data="{ open: false }">
    <button @click="open=!open" class="w-full flex items-center justify-between px-4 py-3.5">
      <span class="font-semibold text-gray-900">Anpassat avdrag / bonus</span>
      <svg :class="open ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="open" x-cloak class="px-4 pb-4 border-t border-gray-50 pt-3">
      <div class="flex gap-2">
        <input type="number" id="custom-amount" placeholder="t.ex. -10 eller +5" step="0.5"
               class="flex-1 px-3 py-3 border border-gray-200 rounded-xl text-base focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <input type="text" id="custom-desc" placeholder="Anledning"
               class="flex-1 px-3 py-3 border border-gray-200 rounded-xl text-base focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <button onclick="addCustomAdjustment(<?= $child['id'] ?>, '<?= addslashes($selDate) ?>')"
                class="touch-btn px-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-semibold transition-colors">+</button>
      </div>
    </div>
  </div>

  <!-- Week adjustments list -->
  <?php if (!empty($weekAdj)): ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-4 py-3 border-b border-gray-50">
      <h2 class="font-semibold text-gray-900">Veckans händelser</h2>
    </div>
    <div class="divide-y divide-gray-50" id="adj-list">
      <?php foreach ($weekAdj as $a):
        $amt = (float)$a['amount'];
        $d   = (int)date('N', strtotime($a['log_date'])) - 1;
      ?>
      <div class="flex items-center gap-3 px-4 py-3 adj-row" id="adj-<?= $a['id'] ?>">
        <span class="text-sm font-bold <?= $amt >= 0 ? 'text-green-600' : 'text-red-500' ?> w-16 text-right flex-shrink-0">
          <?= $amt > 0 ? '+' : '' ?><?= formatKr($amt) ?>
        </span>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-gray-800 font-medium truncate"><?= htmlspecialchars($a['description']) ?></p>
          <p class="text-xs text-gray-400"><?= SHORT_DAYS[$d] ?> <?= date('j/n', strtotime($a['log_date'])) ?></p>
        </div>
        <button onclick="deleteAdjustment(<?= $a['id'] ?>, <?= $child['id'] ?>)"
                class="p-2 text-gray-300 hover:text-red-500 rounded-lg hover:bg-red-50 transition-colors flex-shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Generate summary -->
  <div class="mb-4">
    <?php if (!$summary): ?>
    <button onclick="generateSummary(<?= $child['id'] ?>, '<?= $ws ?>')"
            class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-4 rounded-2xl text-base transition-colors shadow-sm">
      📋 Generera veckosammanställning
    </button>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-gray-900">Veckosammanställning</h2>
        <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?= STATUS_LABELS[$summary['status']]['class'] ?>"><?= STATUS_LABELS[$summary['status']]['label'] ?></span>
      </div>
      <div class="space-y-2 text-sm mb-4">
        <div class="flex justify-between"><span class="text-gray-500">Bas veckopeng</span><span class="font-medium"><?= formatKr($summary['base_amount']) ?></span></div>
        <div class="flex justify-between"><span class="text-gray-500">Avdrag / Bonus</span>
          <span class="font-medium <?= $summary['total_adjustments'] >= 0 ? 'text-green-600' : 'text-red-500' ?>">
            <?= $summary['total_adjustments'] > 0 ? '+' : '' ?><?= formatKr($summary['total_adjustments']) ?>
          </span>
        </div>
        <div class="flex justify-between text-base font-bold border-t border-gray-100 pt-2">
          <span>Totalt</span><span><?= formatKr($summary['final_amount']) ?></span>
        </div>
        <div class="flex justify-between text-xs text-gray-400">
          <span>Krav uppfyllda</span><span><?= $summary['requirements_completed'] ?>/<?= $summary['requirements_total'] ?></span>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <?php foreach (['paid' => '✅ Betald', 'owed' => '⚠️ Skyldig'] as $st => $label): ?>
        <button onclick="updateStatus(<?= $summary['id'] ?>, '<?= $st ?>')"
                class="py-2.5 rounded-xl text-xs font-semibold transition-colors <?= $summary['status'] === $st ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
          <?= $label ?>
        </button>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($child['swish_number'])):
        $swishNum = preg_replace('/[^0-9]/', '', $child['swish_number']);
        $swishData = urlencode(json_encode([
            'version' => 1,
            'payee'   => ['value' => $swishNum, 'editable' => false],
            'amount'  => ['value' => (float)$summary['final_amount'], 'editable' => false],
            'message' => ['value' => 'Veckopeng v.' . date('W', strtotime($ws)), 'editable' => true],
        ]));
      ?>
      <a href="swish://payment?data=<?= $swishData ?>"
         class="mt-2 flex items-center justify-center gap-2 w-full py-3.5 rounded-xl bg-[#00A95C] hover:bg-[#009050] text-white font-bold text-base transition-colors">
        <svg viewBox="0 0 24 24" class="w-5 h-5 fill-white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
        Betala med Swish
      </a>
      <?php endif; ?>
    </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="text-center">
    <a href="/history.php?id=<?= $id ?>" class="text-sm text-indigo-600 font-medium hover:text-indigo-800">
      Visa veckohistorik →
    </a>
  </div>
</main>

<script>
const CSRF = '<?= htmlspecialchars(csrf()) ?>';

// Requirement toggle
document.querySelectorAll('.req-row').forEach(row => {
  row.addEventListener('click', async () => {
    const reqId   = row.dataset.reqId;
    const date    = row.dataset.date;
    const childId = row.dataset.childId;
    const cb      = row.querySelector('.req-checkbox');
    const box     = row.querySelector('.req-check-box');
    const lbl     = row.querySelector('.req-label');
    const newVal  = !cb.checked;

    cb.checked = newVal;
    box.classList.toggle('bg-green-500', newVal);
    box.classList.toggle('border-green-500', newVal);
    box.classList.toggle('border-gray-300', !newVal);
    box.querySelector('svg').classList.toggle('opacity-0', !newVal);
    lbl.classList.toggle('line-through', newVal);
    lbl.classList.toggle('text-gray-400', newVal);

    const r = await fetch('/api/toggle_req.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF},
      body: JSON.stringify({child_id:+childId, requirement_id:+reqId, date, completed:newVal})
    }).then(r=>r.json()).catch(()=>({error:'Nätverksfel'}));

    if (r.error) { cb.checked = !newVal; alert(r.error); return; }
    if (r.final !== undefined) {
      document.getElementById('final-amount').textContent = r.final_fmt;
    }
  });
});

async function addAdjustment(childId, typeId, date, desc, amount) {
  const r = await fetch('/api/add_adjustment.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF},
    body: JSON.stringify({child_id:childId, deduction_type_id:typeId, date, description:desc, amount})
  }).then(r=>r.json()).catch(()=>({error:'Nätverksfel'}));
  if (r.error) { alert(r.error); return; }
  location.reload();
}

async function addCustomAdjustment(childId, date) {
  const amount = parseFloat(document.getElementById('custom-amount').value);
  const desc   = document.getElementById('custom-desc').value.trim();
  if (isNaN(amount)) { alert('Ange ett belopp, t.ex. -10 eller +5'); return; }
  if (!desc) { alert('Ange en anledning'); return; }
  await addAdjustment(childId, null, date, desc, amount);
}

async function deleteAdjustment(adjId, childId) {
  if (!confirm('Ta bort denna händelse?')) return;
  const r = await fetch('/api/delete_adjustment.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF},
    body: JSON.stringify({adjustment_id:adjId, child_id:childId})
  }).then(r=>r.json()).catch(()=>({error:'Nätverksfel'}));
  if (r.error) { alert(r.error); return; }
  document.getElementById('adj-'+adjId)?.remove();
  if (r.final_fmt) document.getElementById('final-amount').textContent = r.final_fmt;
}

async function addMinutes(childId, reqId, date, delta, target) {
  const todayEl = document.getElementById('min-today-' + reqId);
  const current = parseInt(todayEl.textContent) || 0;
  const newVal  = Math.max(0, current + delta);

  const r = await fetch('/api/log_minutes.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF},
    body: JSON.stringify({child_id: childId, requirement_id: reqId, date, minutes: newVal})
  }).then(r => r.json()).catch(() => ({error: 'Nätverksfel'}));

  if (r.error) { alert(r.error); return; }

  todayEl.textContent = r.minutes_today + ' min';
  const weekEl = document.getElementById('min-week-' + reqId);
  weekEl.textContent = r.minutes_week + '/' + r.target + ' min';
  weekEl.className = 'text-sm font-semibold ' + (r.completed ? 'text-green-600' : 'text-gray-500');

  const bar = document.getElementById('min-bar-' + reqId);
  const pct = target > 0 ? Math.min(100, Math.round(100 * r.minutes_week / r.target)) : 0;
  bar.style.width = pct + '%';
  bar.className = 'h-2 rounded-full transition-all ' + (r.completed ? 'bg-green-500' : 'bg-indigo-500');

  // Update checkmark in the row
  const row = document.getElementById('min-row-' + reqId);
  if (row) {
    const circle = row.querySelector('.rounded-full.border-2');
    const check  = row.querySelector('svg');
    if (r.completed) {
      circle.classList.replace('border-gray-300', 'border-green-500');
      circle.classList.add('bg-green-500');
      check.classList.remove('opacity-0');
    } else {
      circle.classList.remove('bg-green-500', 'border-green-500');
      circle.classList.add('border-gray-300');
      check.classList.add('opacity-0');
    }
  }
}

async function generateSummary(childId, weekStart) {
  const r = await fetch('/api/generate_summary.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF},
    body: JSON.stringify({child_id:childId, week_start:weekStart})
  }).then(r=>r.json()).catch(()=>({error:'Nätverksfel'}));
  if (r.error) { alert(r.error); return; }
  location.reload();
}

async function updateStatus(summaryId, status) {
  const r = await fetch('/api/update_status.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF},
    body: JSON.stringify({summary_id:summaryId, status})
  }).then(r=>r.json()).catch(()=>({error:'Nätverksfel'}));
  if (r.error) { alert(r.error); return; }
  location.reload();
}
</script>
<?php pageFoot(); ?>
