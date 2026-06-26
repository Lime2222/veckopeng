<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';

startSession();
if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Veckopeng – Logga in</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🪙</text></svg>">
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center p-4">
<div class="w-full max-w-md">
  <div class="text-center mb-8">
    <span class="text-6xl">🪙</span>
    <h1 class="text-3xl font-bold text-indigo-700 mt-2">Veckopeng</h1>
    <p class="text-gray-500 mt-1">Hantera barnens veckopeng enkelt</p>
  </div>

  <?php if ($error): ?>
  <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div x-data="{ tab: 'login' }" x-init="$el.removeAttribute('x-cloak')">
    <div class="flex bg-white rounded-2xl shadow-sm border border-gray-100 p-1 mb-4">
      <button @click="tab='login'" :class="tab==='login' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700'" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all">Logga in</button>
      <button @click="tab='register'" :class="tab==='register' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700'" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all">Skapa konto</button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <form x-show="tab==='login'" action="/api/auth_login.php" method="POST" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">E-post</label>
          <input type="email" name="email" required autocomplete="email"
                 class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Lösenord</label>
          <input type="password" name="password" required autocomplete="current-password"
                 class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base">
        </div>
        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3.5 rounded-xl text-base transition-colors">
          Logga in
        </button>
      </form>

      <form x-show="tab==='register'" action="/api/auth_register.php" method="POST" class="space-y-4" style="display:none">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf()) ?>">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Ditt namn</label>
          <input type="text" name="name" required autocomplete="name"
                 class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">E-post</label>
          <input type="email" name="email" required autocomplete="email"
                 class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Lösenord</label>
          <input type="password" name="password" required minlength="8" autocomplete="new-password"
                 class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-base">
        </div>
        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3.5 rounded-xl text-base transition-colors">
          Skapa konto
        </button>
      </form>
    </div>
  </div>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
