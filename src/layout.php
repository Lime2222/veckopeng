<?php
function pageHead(string $title): void { ?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> – Veckoswisha</title>
<meta name="description" content="Hantera barnens veckopeng enkelt">
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
      <span>🪙</span><span>Veckoswisha</span>
    </a>
    <div class="flex items-center gap-3">
      <?php if (isAdmin()): ?>
      <a href="/admin.php" title="Admin" class="p-2 text-gray-500 hover:text-indigo-600 rounded-full hover:bg-indigo-50 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
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
