<?php
// src/security/google.php — helpers OAuth Google

declare(strict_types=1);

namespace SoDrink\Security;

use RuntimeException;

/**
 * Retourne la configuration OAuth Google si disponible.
 */
function google_oauth_config(): ?array
{
    $clientId = trim((string) (\env('GOOGLE_CLIENT_ID') ?? ''));
    $clientSecret = trim((string) (\env('GOOGLE_CLIENT_SECRET') ?? ''));
    if ($clientId === '' || $clientSecret === '') {
        return null;
    }

    $redirect = trim((string) (\env('GOOGLE_REDIRECT_URI') ?? ''));
    if ($redirect === '') {
        $redirect = google_default_redirect_uri();
    }

    return [
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirect,
    ];
}

/**
 * Indique si l'authentification Google est correctement configurée.
 */
function google_oauth_is_configured(): bool
{
    return google_oauth_config() !== null;
}

/**
 * Construit l'URL de redirection (absolue) vers notre callback Google.
 */
function google_default_redirect_uri(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = defined('WEB_BASE') ? (string) WEB_BASE : '';
    $path = rtrim($base, '/') . '/auth/google.php';
    if ($base === '' || $base === '/') {
        $path = '/auth/google.php';
    }

    return sprintf('%s://%s%s', $scheme, $host, $path);
}

/**
 * URL d'autorisation Google.
 */
function google_authorize_url(array $config, string $state, array $params = []): string
{
    $query = array_merge([
        'client_id'     => $config['client_id'],
        'redirect_uri'  => $config['redirect_uri'],
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
        'access_type'   => 'online',
    ], $params);

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

/**
 * Échange un code d'autorisation contre un jeton d'accès.
 *
 * @return array<string, mixed>
 */
function google_exchange_code(array $config, string $code): array
{
    $payload = http_build_query([
        'code'          => $code,
        'client_id'     => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri'  => $config['redirect_uri'],
        'grant_type'    => 'authorization_code',
    ], '', '&', PHP_QUERY_RFC3986);

    [$status, $json] = google_http_json('https://oauth2.googleapis.com/token', [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
        'content'       => $payload,
        'timeout'       => 8,
        'ignore_errors' => true,
    ]);

    if ($status !== 200 || !is_array($json) || empty($json['access_token'])) {
        throw new RuntimeException('Impossible de récupérer le jeton Google.');
    }

    return $json;
}

/**
 * Récupère le profil utilisateur via l'endpoint OpenID Connect.
 *
 * @return array<string, mixed>
 */
function google_fetch_userinfo(string $accessToken): array
{
    [$status, $json] = google_http_json('https://openidconnect.googleapis.com/v1/userinfo', [
        'method'        => 'GET',
        'header'        => "Authorization: Bearer {$accessToken}\r\nAccept: application/json\r\n",
        'timeout'       => 8,
        'ignore_errors' => true,
    ]);

    if ($status !== 200 || !is_array($json) || empty($json['sub'])) {
        throw new RuntimeException('Impossible de récupérer les informations Google.');
    }

    return $json;
}

/**
 * Réalise une requête HTTP et retourne [status, json].
 *
 * @return array{0:int,1:array<string,mixed>|null}
 */
function google_http_json(string $url, array $httpOptions): array
{
    $context = stream_context_create(['http' => $httpOptions]);
    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int) $m[1];
            break;
        }
    }

    if ($body === false) {
        throw new RuntimeException('Requête HTTP vers Google échouée.');
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Réponse JSON invalide depuis Google.');
    }

    return [$status, $data];
}
