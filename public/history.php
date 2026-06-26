<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/layout.php';

$user    = requireAuth();
$id      = (int)($_GET['id'] ?? 0);
$child   = requireChildOwnership($id, $user['id']);
$history = getWeeklyHistory($child['id'], 26);

pageHead('Historik – ' . $child['name']);
pageNav($user['name'], $child['id']);
?>
<main class="max-w-lg mx-auto px-4 py-6">
  <div class="flex items-center gap-3 mb-6">
    <a href="/child.php?id=<?= $id ?>" class="p-2 rounded-xl text-gray-500 hover:bg-gray-100">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <h1 class="text-xl font-bold text-gray-900">Historik – <?= htmlspecialchars($child['name']) ?></h1>
  </div>

  <?php if (empty($history)): ?>
  <div class="bg-white rounded-2xl border border-dashed border-gray-200 p-10 text-center">
    <span class="text-4xl block mb-2">📋</span>
    <p class="text-gray-500">Inga sammanställningar ännu.</p>
    <p class="text-sm text-gray-400 mt-1">Generera en veckosammanställning från veckovyn.</p>
  </div>
  <?php else: ?>

  <?php
  $totalPaid  = 0;
  $totalOwed  = 0;
  foreach ($history as $s) {
    if ($s['status'] === 'paid' || $s['status'] === 'sent') $totalPaid += $s['final_amount'];
    if ($s['status'] === 'owed') $totalOwed += $s['final_amount'];
  }
  ?>
  <div class="grid grid-cols-2 gap-3 mb-5">
    <div class="bg-green-50 rounded-2xl p-4 border border-green-100">
      <p class="text-xs text-green-600 font-medium">Totalt utbetalt</p>
      <p class="text-xl font-bold text-green-700 mt-0.5"><?= formatKr($totalPaid) ?></p>
    </div>
    <div class="bg-red-50 rounded-2xl p-4 border border-red-100">
      <p class="text-xs text-red-600 font-medium">Utestående</p>
      <p class="text-xl font-bold text-red-700 mt-0.5"><?= formatKr($totalOwed) ?></p>
    </div>
  </div>

  <div class="space-y-3">
    <?php foreach ($history as $s):
      $sl = STATUS_LABELS[$s['status']] ?? STATUS_LABELS['pending'];
      $ws = $s['week_start'];
      $we = $s['week_end'];
    ?>
    <a href="/child.php?id=<?= $id ?>&week=<?= $ws ?>"
       class="block bg-white rounded-2xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition-shadow">
      <div class="flex items-start justify-between">
        <div>
          <p class="font-semibold text-gray-900">
            Vecka <?= date('W', strtotime($ws)) ?> · <?= date('j', strtotime($ws)) ?>–<?= date('j M', strtotime($we)) ?>
          </p>
          <div class="flex items-center gap-3 mt-1.5 text-sm">
            <span class="text-gray-500">Bas: <?= formatKr($s['base_amount']) ?></span>
            <?php if ((float)$s['total_adjustments'] !== 0.0): ?>
            <span class="<?= (float)$s['total_adjustments'] >= 0 ? 'text-green-600' : 'text-red-500' ?>">
              <?= (float)$s['total_adjustments'] > 0 ? '+' : '' ?><?= formatKr((float)$s['total_adjustments']) ?>
            </span>
            <?php endif; ?>
          </div>
          <p class="text-xs text-gray-400 mt-1">
            Krav: <?= $s['requirements_completed'] ?>/<?= $s['requirements_total'] ?>
          </p>
        </div>
        <div class="text-right">
          <p class="text-xl font-bold text-gray-900"><?= formatKr($s['final_amount']) ?></p>
          <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?= $sl['class'] ?> mt-1 inline-block"><?= $sl['label'] ?></span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
<?php pageFoot(); ?>
