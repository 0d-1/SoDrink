<?php
// src/utils/response.php
// Réponses JSON standardisées

declare(strict_types=1);

function json_success(array $data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400, array $extra = []): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $payload = ['success' => false, 'error' => $message];
    if (!empty($extra)) $payload['details'] = $extra;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}