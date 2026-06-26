<?php
require_once __DIR__ . '/config.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireAuth(): array {
    startSession();
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
