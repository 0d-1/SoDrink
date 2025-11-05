<?php
// public/auth/google.php — démarrage + callback OAuth Google

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/security/auth.php';
require_once __DIR__ . '/../../src/security/google.php';
require_once __DIR__ . '/../../src/domain/Users.php';

use SoDrink\Domain\Users;
use function SoDrink\Security\google_authorize_url;
use function SoDrink\Security\google_exchange_code;
use function SoDrink\Security\google_fetch_userinfo;
use function SoDrink\Security\google_oauth_config;
use function SoDrink\Security\issue_remember_cookie;
use function SoDrink\Security\login_user;

$config = google_oauth_config();
if ($config === null) {
    http_response_code(404);
    echo 'Authentification Google indisponible.';
    exit;
}

$mode = isset($_GET['mode']) ? strtolower((string) $_GET['mode']) : 'login';
if (!in_array($mode, ['login', 'register'], true)) {
    $mode = 'login';
}

$remember = false;
if (isset($_GET['remember'])) {
    $val = strtolower((string) $_GET['remember']);
    $remember = in_array($val, ['1', 'true', 'on', 'yes'], true);
}

// Gestion des annulations directes côté Google
if (isset($_GET['error'])) {
    $err = (string) ($_GET['error_description'] ?? $_GET['error']);
    set_auth_error($err !== '' ? $err : 'Connexion Google annulée.', $mode);
    redirect_to_login();
}

if (!isset($_GET['code'])) {
    start_google_flow($config, $mode, $remember);
    exit;
}

try {
    $userId = complete_google_flow($config, (string) $_GET['code']);
    $rememberSession = ($_SESSION['google_remember'] ?? '') === '1';
    unset($_SESSION['google_remember']);
    if (($rememberSession || $remember) && $userId > 0) {
        issue_remember_cookie($userId);
    }
    clear_oauth_state();
    redirect_to_home();
} catch (RuntimeException $e) {
    set_auth_error($e->getMessage(), $mode);
    clear_oauth_state();
    redirect_to_login();
}

function start_google_flow(array $config, string $mode, bool $remember): void
{
    $state = bin2hex(random_bytes(18));
    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_auth_mode'] = $mode;
    $_SESSION['google_remember'] = $remember ? '1' : '0';

    $params = [];
    if (isset($_GET['login_hint'])) {
        $params['login_hint'] = (string) $_GET['login_hint'];
    }

    $url = google_authorize_url($config, $state, $params);
    header('Location: ' . $url, true, 302);
}

function complete_google_flow(array $config, string $code): int
{
    if (!isset($_SESSION['google_oauth_state'])) {
        throw new RuntimeException('Session OAuth Google absente.');
    }

    $stateExpected = (string) $_SESSION['google_oauth_state'];
    $stateReturned = isset($_GET['state']) ? (string) $_GET['state'] : '';
    if ($stateReturned === '' || !hash_equals($stateExpected, $stateReturned)) {
        throw new RuntimeException('État de session invalide. Veuillez réessayer.');
    }

    $tokens = google_exchange_code($config, $code);
    $profile = google_fetch_userinfo((string) $tokens['access_token']);

    $users = new Users();
    $googleId = (string) $profile['sub'];
    $user = $users->findByGoogleId($googleId);

    $email = isset($profile['email']) ? strtolower((string) $profile['email']) : null;
    if (!$user && $email) {
        $user = $users->findByEmail($email);
    }

    if ($user) {
        $updates = [];
        if (empty($user['google_id'])) {
            $updates['google_id'] = $googleId;
        }
        if ($email && empty($user['email'])) {
            $updates['email'] = $email;
        }
        if ($updates) {
            $updates['auth_provider'] = 'google';
            $users->update((int) $user['id'], $updates);
            $user = $users->getById((int) $user['id']);
        }
    } else {
        $pseudo = generate_pseudo_from_profile($users, $profile, $email);
        $user = $users->create([
            'pseudo'        => $pseudo,
            'prenom'        => (string) ($profile['given_name'] ?? ''),
            'nom'           => (string) ($profile['family_name'] ?? ''),
            'instagram'     => null,
            'pass_hash'     => '',
            'avatar'        => null,
            'email'         => $email,
            'google_id'     => $googleId,
            'auth_provider' => 'google',
        ]);
    }

    if (!$user) {
        throw new RuntimeException('Impossible de créer ou récupérer votre compte.');
    }

    $userId = (int) $user['id'];
    login_user($userId, $user['role'] ?? 'user', $user['pseudo'], $user['avatar'] ?? null);
    return $userId;
}

function generate_pseudo_from_profile(Users $repo, array $profile, ?string $email): string
{
    $candidates = [];
    $given = (string) ($profile['given_name'] ?? '');
    $family = (string) ($profile['family_name'] ?? '');
    $name = (string) ($profile['name'] ?? '');

    if ($given !== '') {
        $candidates[] = $given . ($family !== '' ? '.' . mb_substr($family, 0, 1) : '');
        $candidates[] = $given;
    }
    if ($name !== '') {
        $candidates[] = $name;
    }
    if ($email) {
        $local = substr($email, 0, (int) strpos($email, '@')) ?: $email;
        $candidates[] = $local;
    }
    $candidates[] = 'membre';

    $candidates = array_filter(array_map('trim', $candidates), fn ($c) => $c !== '');

    foreach ($candidates as $candidate) {
        $normalized = normalize_pseudo_candidate($candidate);
        if ($normalized === '') {
            continue;
        }
        if (!$repo->findByPseudo($normalized)) {
            return $normalized;
        }
        $suffix = 1;
        $base = $normalized;
        if (strlen($base) > 17) {
            $base = substr($base, 0, 17);
        }
        while (true) {
            $try = $base . $suffix;
            if (strlen($try) > 20) {
                $base = substr($base, 0, max(1, 20 - strlen((string) $suffix)));
                $try = $base . $suffix;
            }
            if (!$repo->findByPseudo($try)) {
                return $try;
            }
            $suffix++;
            if ($suffix > 999) {
                break;
            }
        }
    }

    return 'membre' . random_int(1000, 9999);
}

function normalize_pseudo_candidate(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($converted !== false) {
        $value = $converted;
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
    $value = trim($value, '._-');
    if ($value === '') {
        return '';
    }
    if (strlen($value) > 20) {
        $value = substr($value, 0, 20);
    }
    while (strlen($value) < 3) {
        $value .= 'x';
    }
    return $value;
}

function set_auth_error(string $message, string $mode): void
{
    $_SESSION['google_auth_error'] = $message;
    $_SESSION['google_auth_mode'] = $mode;
}

function clear_oauth_state(): void
{
    unset($_SESSION['google_oauth_state'], $_SESSION['google_remember']);
}

function redirect_to_login(): void
{
    $base = defined('WEB_BASE') ? (string) WEB_BASE : '';
    $url = ($base === '') ? '/login.php' : rtrim($base, '/') . '/login.php';
    header('Location: ' . $url, true, 302);
    exit;
}

function redirect_to_home(): void
{
    unset($_SESSION['google_auth_error'], $_SESSION['google_auth_mode']);
    $base = defined('WEB_BASE') ? (string) WEB_BASE : '';
    $url = ($base === '' || $base === '/') ? '/' : rtrim($base, '/') . '/';
    header('Location: ' . $url, true, 302);
    exit;
}
