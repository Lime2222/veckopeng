<?php
define('APP_ROOT', dirname(__DIR__));

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $host = getenv('DB_HOST') ?: 'postgres';
        $port = getenv('DB_PORT') ?: '5432';
        $name = getenv('DB_NAME') ?: 'veckopeng';
        $user = getenv('DB_USER') ?: 'veckopeng';
        $pass = getenv('DB_PASS') ?: 'veckopeng_secret';
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$name", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(): bool {
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrf(), $token);
}

function weekStart(?string $date = null): string {
    $d = $date ? new DateTime($date) : new DateTime();
    $n = (int)$d->format('N');
    if ($n > 1) $d->modify('-' . ($n - 1) . ' days');
    return $d->format('Y-m-d');
}

function weekDates(string $ws): array {
    $dates = [];
    $d = new DateTime($ws);
    for ($i = 0; $i < 7; $i++) {
        $dates[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
    return $dates;
}

function formatKr(float $amount): string {
    return number_format($amount, 2, ',', ' ') . ' kr';
}

function jsonOut(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
