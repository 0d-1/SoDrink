<?php
// src/security/csrf.php
// Génération et vérification des tokens CSRF

declare(strict_types=1);

namespace SoDrink\Security;

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function extract_csrf_from_request(): ?string {
    $h = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($h !== '') return $h;
    if (($_POST['csrf_token'] ?? '') !== '') return (string)$_POST['csrf_token'];
    // JSON body
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (is_array($data) && isset($data['csrf_token'])) return (string)$data['csrf_token'];
    }
    return null;
}

function require_csrf(): void {
    $sent = extract_csrf_from_request();
    if (!$sent || !hash_equals((string)($_SESSION['_csrf'] ?? ''), $sent)) {
        http_response_code(419); // Authentication Timeout / CSRF invalid
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'CSRF token invalide']);
        exit;
    }
}