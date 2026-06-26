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
        header('Location: /index.php');
        exit;
    }
    return ['id' => (int)$_SESSION['user_id'], 'name' => $_SESSION['user_name']];
}

function requireChildOwnership(int $childId, int $userId): array {
    $stmt = db()->prepare('SELECT * FROM children WHERE id = ? AND user_id = ?');
    $stmt->execute([$childId, $userId]);
    $child = $stmt->fetch();
    if (!$child) {
        http_response_code(403);
        die('Ej behörig');
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
