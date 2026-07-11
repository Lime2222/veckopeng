<?php
require_once __DIR__ . '/config.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function tryRememberLogin(): void {
    $token = $_COOKIE['remember_token'] ?? '';
    if (!$token) return;
    $hash = hash('sha256', $token);
    $stmt = db()->prepare('
        SELECT u.id, u.name FROM remember_tokens rt
        JOIN users u ON u.id = rt.user_id
        WHERE rt.token_hash = ? AND rt.expires_at > NOW()
    ');
    $stmt->execute([$hash]);
    $user = $stmt->fetch();
    if (!$user) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        return;
    }
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    // Extend token expiry another 30 days
    db()->prepare('UPDATE remember_tokens SET expires_at = NOW() + INTERVAL \'30 days\' WHERE token_hash = ?')
        ->execute([$hash]);
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('remember_token', $token, time() + 30 * 86400, '/', '', $secure, true);
}

function logActivity(int $userId): void {
    try {
        $page = mb_substr(basename($_SERVER['SCRIPT_NAME'] ?? ''), 0, 100);
        if ($page === '') return;
        db()->prepare('INSERT INTO activity_log (user_id, page) VALUES (?, ?)')
            ->execute([$userId, $page]);
        if (random_int(1, 50) === 1) {
            db()->exec("DELETE FROM activity_log WHERE created_at < NOW() - INTERVAL '90 days'");
        }
    } catch (Throwable $e) {
        // Loggning får aldrig stoppa sidan (t.ex. innan migrationen körts)
    }
}

function requireAuth(): array {
    startSession();
    if (empty($_SESSION['user_id'])) {
        tryRememberLogin();
    }
    if (empty($_SESSION['user_id'])) {
        if (!empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/index.php') {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }
        header('Location: /index.php');
        exit;
    }
    logActivity((int)$_SESSION['user_id']);
    return ['id' => (int)$_SESSION['user_id'], 'name' => $_SESSION['user_name']];
}

// Checks family_members — both owner and co-parent pass
function requireChildOwnership(int $childId, int $userId): array {
    $stmt = db()->prepare('
        SELECT c.*, fm.role
        FROM children c
        JOIN family_members fm ON fm.child_id = c.id AND fm.user_id = ?
        WHERE c.id = ?
    ');
    $stmt->execute([$userId, $childId]);
    $child = $stmt->fetch();
    if (!$child) {
        http_response_code(403);
        die('Ej behörig');
    }
    return $child;
}

// Only the owner may delete the child or remove members
function requireChildOwner(int $childId, int $userId): array {
    $child = requireChildOwnership($childId, $userId);
    if ($child['role'] !== 'owner') {
        http_response_code(403);
        die('Endast ägaren kan utföra denna åtgärd');
    }
    return $child;
}

// Site admins, identified by account email
const ADMIN_EMAILS = ['eemilandersson@gmail.com'];

function isAdmin(?int $userId = null): bool {
    static $cache = [];
    $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);
    if (!$userId) return false;
    if (!isset($cache[$userId])) {
        $stmt = db()->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $email = strtolower((string)$stmt->fetchColumn());
        $cache[$userId] = in_array($email, ADMIN_EMAILS, true);
    }
    return $cache[$userId];
}

function requireAdmin(): array {
    $user = requireAuth();
    if (!isAdmin($user['id'])) {
        header('Location: /dashboard.php');
        exit;
    }
    return $user;
}

function clientIp(): string {
    // Behind Caddy the real client is in X-Forwarded-For
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff) return trim(explode(',', $xff)[0]);
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function logLoginAttempt(string $email, bool $success): void {
    try {
        db()->prepare('INSERT INTO login_attempts (email, ip, user_agent, success) VALUES (?, ?, ?, ?)')
            ->execute([
                mb_substr($email, 0, 255),
                mb_substr(clientIp(), 0, 45),
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                $success ? 'true' : 'false',
            ]);
        if ($success && random_int(1, 20) === 1) {
            db()->exec("DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL '180 days'");
        }
    } catch (Throwable $e) {
        // Loggning får aldrig stoppa en inloggning (t.ex. innan migrationen körts)
    }
}

function requireApiAuth(): array {
    startSession();
    if (empty($_SESSION['user_id'])) {
        jsonOut(['error' => 'Ej inloggad'], 401);
    }
    logActivity((int)$_SESSION['user_id']);
    return ['id' => (int)$_SESSION['user_id'], 'name' => $_SESSION['user_name']];
}
