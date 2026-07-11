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
$isLocked       = $summary !== false;
$isChildUser    = $child['role'] === 'child';
$canSelfReport  = (bool)($child['child_can_self_report'] ?? false);
$canSelfAdjust  = (bool)($child['child_can_self_adjust']  ?? false);
$reqLocked      = $isLocked || ($isChildUser && !$canSelfReport);

$prevWeek = (new DateTime($ws))->modify('-7 days')->format('Y-m-d');
$nextWeek = (new DateTime($ws))->modify('+7 days')->format('Y-m-d');
$isCurrentWeek = $ws === weekStart();

$allChildren = !$isChildUser ? getChildren($user['id']) : [];

pageHead($child['name']);
pageNav($user['name'], $child['id'], $isChildUser);
?>
<main class="max-w-lg mx-auto px-4 py-4" x-data="weekView()" x-init="init()">

  <?php if (!$isChildUser): ?>
  <!-- Child switcher + settings -->
  <div class="flex items-center gap-2 mb-4 overflow-x-auto pb-1">
    <?php if (count($allChildren) > 1): foreach ($allChildren as $c): ?>
    <?php $isActive = $c['id'] === $child['id']; ?>
    <a href="/child.php?id=<?= $c['id'] ?>"
       class="flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-full font-semibold text-sm transition-all <?= $isActive ? 'text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300' ?>"
       style="<?= $isActive ? 'background-color:' . htmlspecialchars($c['avatar_color']) : '' ?>">
      <span class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold <?= $isActive ? 'bg-white bg-opacity-25 text-white' : 'text-white' ?>"
            style="<?= !$isActive ? 'background-color:' . htmlspecialchars($c['avatar_color']) : '' ?>">
        <?= mb_substr($c['name'], 0, 1) ?>
      </span>
      <?= htmlspecialchars($c['name']) ?>
    </a>
    <?php endforeach; endif; ?>
    <a href="/settings.php?id=<?= $child['id'] ?>" title="Inställningar för <?= htmlspecialchars($child['name']) ?>"
       class="ml-auto flex-shrink-0 p-2 text-gray-400 hover:text-indigo-600 rounded-full hover:bg-indigo-50 transition-colors">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    </a>
  </div>
  <?php endif; ?>

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
        <?php if (!empty($totals['penalty'])): ?>
        <p class="text-xs text-red-300">Missade krav: −<?= formatKr($totals['penalty']) ?></p>
        <?php endif; ?>
        <?php if (!empty($totals['screen_fee'])): ?>
        <p class="text-xs text-red-300">📱 Skärmtid över: −<?= formatKr($totals['screen_fee']) ?></p>
        <?php endif; ?>
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
      <span class="text-sm text-indigo-200">🔒 Veckan är stängd</span>
      <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white/20 text-white"><?= $sl['label'] ?></span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Day selector -->
  <div class="flex gap-1.5 mb-4 pb-1">
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
       class="flex-1 min-w-0 flex flex-col items-center py-2 rounded-xl font-medium text-sm transition-all <?= $isSel ? 'bg-indigo-600 text-white shadow-md' : ($isToday ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-white text-gray-600 border border-gray-100') ?>">
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
        <?php if (!$reqLocked): ?>
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
        <?php else: ?>
        <div class="flex items-center gap-2 opacity-50">
          <span class="text-xs text-gray-400">Loggade minuter idag:</span>
          <span class="text-sm font-bold text-gray-700"><?= (int)$log['minutes_today'] ?> min</span>
        </div>
        <?php endif; ?>
      </div>

      <?php else: ?>
      <?php if ($reqLocked): ?>
      <div class="flex items-center gap-4 px-4 py-4 opacity-60">
        <div class="w-7 h-7 rounded-full border-2 flex items-center justify-center flex-shrink-0 <?= $log['completed'] ? 'bg-green-500 border-green-500' : 'border-gray-300' ?>">
          <svg class="w-4 h-4 text-white <?= $log['completed'] ? '' : 'opacity-0' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <span class="text-gray-800 font-medium <?= $log['completed'] ? 'line-through text-gray-400' : '' ?>"><?= htmlspecialchars($log['name']) ?></span>
          <?php if ($log['frequency'] === 'weekly'): ?>
          <span class="ml-1.5 text-xs font-medium px-1.5 py-0.5 rounded bg-purple-100 text-purple-600">Vecka</span>
          <?php endif; ?>
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
      <?php endif; ?>

      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Screen time (per kategori) -->
  <?php if (!empty($totals['screen_enabled'])):
    $screenTodayByCat = [];
    try {
        $stmtST = db()->prepare('SELECT category, minutes FROM screen_logs WHERE child_id = ? AND log_date = ?');
        $stmtST->execute([$child['id'], $selDate]);
        foreach ($stmtST->fetchAll() as $stRow) $screenTodayByCat[$stRow['category']] = (int)$stRow['minutes'];
    } catch (Throwable $e) {}
    $sAdj  = (int)$totals['screen_adj'];
    $sOver = (int)$totals['screen_over'];
  ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
      <h2 class="font-semibold text-gray-900">📱 Skärmtid</h2>
      <?php if ($sOver > 0): ?>
      <span class="text-xs font-semibold text-red-500"><?= formatMin($sOver) ?> över<?= !empty($totals['screen_fee']) ? ' → −' . formatKr($totals['screen_fee']) : '' ?></span>
      <?php elseif ($sAdj !== 0): ?>
      <span class="text-xs font-semibold text-purple-500">Bonuspott: <?= $sAdj > 0 ? '+' : '' ?><?= $sAdj ?> min</span>
      <?php else: ?>
      <span class="text-xs text-gray-400">per vecka, dagsbudget × 7</span>
      <?php endif; ?>
    </div>
    <div class="divide-y divide-gray-50">
      <?php foreach ($totals['screen_cats'] as $cat => $sc):
        [$catIcon, $catLabel] = SCREEN_CATS[$cat] ?? ['📱', $cat];
        $pct   = $sc['pool'] > 0 ? min(100, round(100 * $sc['used'] / $sc['pool'])) : 100;
        $bar   = $sc['used'] > $sc['pool'] ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-400' : 'bg-purple-500');
        $left  = $sc['pool'] - $sc['used'];
        $today = $screenTodayByCat[$cat] ?? 0;
      ?>
      <div class="px-4 py-3">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-sm font-semibold text-gray-800"><?= $catIcon ?> <?= $catLabel ?>
            <span class="text-xs font-normal text-gray-400 ml-1"><?= $sc['daily'] ?> min/dag</span>
          </span>
          <?php if ($left >= 0): ?>
          <span class="text-xs font-semibold text-green-600"><?= formatMin($left) ?> kvar</span>
          <?php else: ?>
          <span class="text-xs font-semibold text-red-500"><?= formatMin(-$left) ?> över</span>
          <?php endif; ?>
        </div>
        <div class="bg-gray-100 rounded-full h-2 mb-2">
          <div class="h-2 rounded-full transition-all <?= $bar ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <?php if (!$reqLocked): ?>
        <div class="flex items-center gap-1.5">
          <span class="text-xs text-gray-400 mr-1">Idag:</span>
          <?php foreach ([15, 30, 60] as $m): ?>
          <button onclick="logScreen(<?= $child['id'] ?>, '<?= $selDate ?>', '<?= $cat ?>', <?= $m ?>)"
                  class="flex-1 py-2 rounded-xl bg-purple-50 text-purple-700 text-xs font-bold hover:bg-purple-100 active:scale-95 transition-all">
            +<?= $m ?>
          </button>
          <?php endforeach; ?>
          <span class="text-xs font-bold text-gray-700 px-1 min-w-[48px] text-center" id="screen-today-<?= $cat ?>"><?= $today ?> min</span>
          <button onclick="logScreen(<?= $child['id'] ?>, '<?= $selDate ?>', '<?= $cat ?>', -15)"
                  class="px-2.5 py-2 rounded-xl bg-red-50 text-red-500 text-xs font-bold hover:bg-red-100 active:scale-95 transition-all">-15</button>
        </div>
        <?php else: ?>
        <div class="flex items-center gap-2 opacity-50">
          <span class="text-xs text-gray-400">Loggat idag:</span>
          <span class="text-xs font-bold text-gray-700"><?= $today ?> min</span>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Quick adjustments -->
  <?php if (!$isLocked && (!$isChildUser || $canSelfAdjust) && !empty($deductTypes)): ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-4 py-3 border-b border-gray-50">
      <h2 class="font-semibold text-gray-900">Snabbknappar</h2>
      <p class="text-xs text-gray-400 mt-0.5">Avdrag &amp; bonus för <?= date('j/n', strtotime($selDate)) ?></p>
    </div>
    <div class="p-4 grid grid-cols-2 gap-2">
      <?php foreach ($deductTypes as $dt):
        $dtUnit = $dt['unit'] ?? 'kr';
        if ($dtUnit === 'min' && empty($totals['screen_enabled'])) continue;
        if ($dtUnit === 'min') {
            $btnCls = 'bg-purple-50 border-purple-100 text-purple-700 hover:bg-purple-100';
            $btnAmt = ($dt['amount'] > 0 ? '+' : '') . (int)$dt['amount'] . ' min';
        } else {
            $btnCls = $dt['amount'] < 0 ? 'bg-red-50 border-red-100 text-red-700 hover:bg-red-100' : 'bg-green-50 border-green-100 text-green-700 hover:bg-green-100';
            $btnAmt = ($dt['amount'] > 0 ? '+' : '') . formatKr((float)$dt['amount']);
        }
      ?>
      <button
        onclick="addAdjustment(<?= $child['id'] ?>, <?= $dt['id'] ?>, '<?= addslashes($selDate) ?>', '<?= htmlspecialchars(addslashes($dt['name'])) ?>', <?= $dt['amount'] ?>, '<?= $dtUnit ?>')"
        class="touch-btn flex flex-col items-center justify-center rounded-xl px-3 py-3 font-semibold text-sm transition-colors border <?= $btnCls ?>">
        <span class="text-base font-bold"><?= $btnAmt ?></span>
        <span class="text-xs mt-0.5 text-center leading-tight"><?= htmlspecialchars($dt['name']) ?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Free-text adjustment -->
  <?php if (!$isLocked && (!$isChildUser || $canSelfAdjust)): ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-4">
    <div class="px-4 py-3 border-b border-gray-50">
      <h2 class="font-semibold text-gray-900">Eget avdrag / bonus</h2>
      <p class="text-xs text-gray-400 mt-0.5">Engångshändelse för <?= date('j/n', strtotime($selDate)) ?> – behöver ingen knapp</p>
    </div>
    <div class="p-4 space-y-2">
      <div class="flex gap-2">
        <input type="number" id="free-amount" min="0" step="0.5" placeholder="Antal" inputmode="decimal"
               class="w-20 px-3 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <?php if (!empty($totals['screen_enabled'])): ?>
        <select id="free-unit" class="px-2 py-3 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="kr">kr</option>
          <option value="min">min 📱</option>
        </select>
        <?php endif; ?>
        <input type="text" id="free-desc" placeholder="Vad hände? t.ex. Hjälpte mormor" maxlength="200"
               class="flex-1 px-3 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent min-w-0">
      </div>
      <div class="grid grid-cols-2 gap-2">
        <button onclick="freeAdjustment(<?= $child['id'] ?>, '<?= addslashes($selDate) ?>', -1)"
                class="touch-btn py-3 rounded-xl bg-red-50 border border-red-100 text-red-700 font-bold text-sm hover:bg-red-100 active:scale-95 transition-all">
          − Avdrag
        </button>
        <button onclick="freeAdjustment(<?= $child['id'] ?>, '<?= addslashes($selDate) ?>', 1)"
                class="touch-btn py-3 rounded-xl bg-green-50 border border-green-100 text-green-700 font-bold text-sm hover:bg-green-100 active:scale-95 transition-all">
          + Bonus
        </button>
      </div>
    </div>
  </div>
  <?php endif; ?>

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
        $aUnit = $a['unit'] ?? 'kr';
      ?>
      <div class="flex items-center gap-3 px-4 py-3 adj-row" id="adj-<?= $a['id'] ?>">
        <span class="text-sm font-bold <?= $aUnit === 'min' ? 'text-purple-600' : ($amt >= 0 ? 'text-green-600' : 'text-red-500') ?> w-16 text-right flex-shrink-0">
          <?= $amt > 0 ? '+' : '' ?><?= $aUnit === 'min' ? (int)$amt . ' min' : formatKr($amt) ?>
        </span>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-gray-800 font-medium truncate"><?= htmlspecialchars($a['description']) ?></p>
          <p class="text-xs text-gray-400"><?= SHORT_DAYS[$d] ?> <?= date('j/n', strtotime($a['log_date'])) ?></p>
        </div>
        <?php if (!$isLocked && (!$isChildUser || $canSelfAdjust)): ?>
        <button onclick="deleteAdjustment(<?= $a['id'] ?>, <?= $child['id'] ?>)"
                class="p-2 text-gray-300 hover:text-red-500 rounded-lg hover:bg-red-50 transition-colors flex-shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Generate summary -->
  <?php if ($isChildUser && $isLocked): ?>
  <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-center text-sm text-amber-800 font-medium">
    Veckan är sammanställd av en förälder
  </div>
  <?php elseif ($isChildUser): ?>
  <?php /* Children don't see the generate/summary section */ ?>
  <?php else: ?>
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
      <?php $sumPenalty = max(0, (float)$summary['base_amount'] + (float)$summary['total_adjustments'] - (float)$summary['final_amount']); ?>
      <div class="space-y-2 text-sm mb-4">
        <div class="flex justify-between"><span class="text-gray-500">Bas veckopeng</span><span class="font-medium"><?= formatKr($summary['base_amount']) ?></span></div>
        <?php if ($sumPenalty > 0): ?>
        <div class="flex justify-between"><span class="text-gray-500">Avdrag krav &amp; skärmtid</span><span class="font-medium text-red-500">−<?= formatKr($sumPenalty) ?></span></div>
        <?php endif; ?>
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
    </div>
    <?php endif; ?>
  </div>
  <?php endif; /* end !$isChildUser summary section */ ?>

  <?php if (!$isChildUser): ?>
  <div class="text-center">
    <a href="/history.php?id=<?= $id ?>" class="text-sm text-indigo-600 font-medium hover:text-indigo-800">
      Visa veckohistorik →
    </a>
  </div>
  <?php endif; ?>

  <?php if ($isChildUser && !$canSelfReport): ?>
  <div class="p-4 bg-gray-50 border border-gray-200 rounded-2xl text-center text-sm text-gray-500">
    Du kan se dina krav men inte ändra dem. Prata med en förälder!
  </div>
  <?php endif; ?>
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

async function addAdjustment(childId, typeId, date, desc, amount, unit = 'kr') {
  const r = await fetch('/api/add_adjustment.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF},
    body: JSON.stringify({child_id:childId, deduction_type_id:typeId, date, description:desc, amount, unit})
  }).then(r=>r.json()).catch(()=>({error:'Nätverksfel'}));
  if (r.error) { alert(r.error); return; }
  location.reload();
}


async function freeAdjustment(childId, date, sign) {
  const amtEl  = document.getElementById('free-amount');
  const descEl = document.getElementById('free-desc');
  const unitEl = document.getElementById('free-unit');
  const unit   = unitEl ? unitEl.value : 'kr';
  const amount = Math.abs(parseFloat((amtEl.value || '').replace(',', '.')));
  const desc   = descEl.value.trim();
  if (!amount || isNaN(amount)) { alert(unit === 'min' ? 'Fyll i antal minuter.' : 'Fyll i ett belopp i kronor.'); amtEl.focus(); return; }
  if (!desc) { alert('Skriv vad som hände.'); descEl.focus(); return; }
  await addAdjustment(childId, null, date, desc, sign * amount, unit);
}

async function logScreen(childId, date, category, delta) {
  const todayEl = document.getElementById('screen-today-' + category);
  const current = parseInt(todayEl.textContent) || 0;
  const newVal  = Math.max(0, current + delta);

  const r = await fetch('/api/log_screen_time.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF},
    body: JSON.stringify({child_id: childId, date, category, minutes: newVal})
  }).then(r => r.json()).catch(() => ({error: 'Nätverksfel'}));

  if (r.error) { alert(r.error); return; }
  location.reload();
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
