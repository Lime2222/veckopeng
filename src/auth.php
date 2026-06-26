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

function requireApiAuth(): array {
    startSession();
    if (empty($_SESSION['user_id'])) {
        jsonOut(['error' => 'Ej inloggad'], 401);
    }
    return ['id' => (int)$_SESSION['user_id'], 'name' => $_SESSION['user_name']];
}
