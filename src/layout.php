<?php
function pageHead(string $title): void { ?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> – Veckopeng</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🪙</text></svg>">
<style>
  [x-cloak] { display: none !important; }
  .touch-btn { min-height: 52px; min-width: 52px; }
</style>
</head>
<body class="bg-gray-50 min-h-screen pb-20">
<?php }

function pageNav(string $userName, int $childId = 0, bool $isChild = false): void { ?>
<header class="bg-white border-b border-gray-100 sticky top-0 z-30">
  <div class="max-w-lg mx-auto px-4 py-3 flex items-center justify-between">
    <a href="/dashboard.php" class="flex items-center gap-2 font-bold text-indigo-700 text-lg">
      <span>🪙</span><span>Veckopeng</span>
    </a>
    <div class="flex items-center gap-3">
      <?php if ($childId && !$isChild): ?>
      <a href="/settings.php?id=<?= $childId ?>" class="p-2 text-gray-500 hover:text-indigo-600 rounded-full hover:bg-indigo-50 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      </a>
      <?php endif; ?>
      <span class="text-sm text-gray-500 hidden sm:block"><?= htmlspecialchars($userName) ?></span>
      <a href="/logout.php" class="text-sm text-gray-500 hover:text-red-600 font-medium px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">Logga ut</a>
    </div>
  </div>
</header>
<?php }

function pageFoot(): void { ?>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
<?php }
