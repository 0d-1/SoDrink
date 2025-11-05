<?php
// src/security/auth.php
declare(strict_types=1);

namespace SoDrink\Security;

use SoDrink\Domain\Users;

/** Session helpers */
function isLoggedIn(): bool { return isset($_SESSION['user_id']); }
function isAdmin(): bool { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }

function require_login(): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false, 'error'=>'Authentification requise']);
        exit;
    }
}

function require_admin(): void {
    if (!isAdmin()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false, 'error'=>'Réservé aux administrateurs']);
        exit;
    }
}

/** Connexion — stocke les infos minimales en session */
function login_user(int $id, string $role, string $pseudo, ?string $avatar = null): void {
    $_SESSION['user_id']  = $id;
    $_SESSION['role']     = $role;
    $_SESSION['pseudo']   = $pseudo;
    if ($avatar) $_SESSION['avatar'] = $avatar;
}

/** Déconnexion (session uniquement; jeton "remember" géré ailleurs) */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, [
            'expires'  => time()-42000,
            'path'     => $params['path'] ?? (defined('WEB_BASE') ? WEB_BASE : '/'),
            'domain'   => $params['domain'] ?? '',
            'secure'   => (bool)($params['secure'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

/* =============================
   "Remember me" — 14 jours
   ============================= */

const REMEMBER_COOKIE = 'sdrk_remember';
const REMEMBER_DAYS   = 14;

/** Options de cookie cohérentes avec l'app en sous-dossier */
function remember_cookie_options(int $expires): array {
    $params = session_get_cookie_params();
    $path   = defined('WEB_BASE') ? (string)WEB_BASE : ($params['path'] ?? '/');
    if ($path === '') $path = '/';
    return [
        'expires'  => $expires,
        'path'     => $path,
        'domain'   => $params['domain'] ?? '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/** Crée un jeton selector+validator, l'enregistre côté serveur et pose le cookie */
function issue_remember_cookie(int $userId): void {
    $selector  = bin2hex(random_bytes(9));   // 18 hex chars
    $validator = bin2hex(random_bytes(32));  // 64 hex chars
    $hash      = hash('sha256', $validator);
    $expiresTs = time() + REMEMBER_DAYS * 24 * 3600;

    $repo = new Users();
    $user = $repo->getById($userId);
    if (!$user) return;

    $tokens = $user['remember_tokens'] ?? [];
    $tokens[] = [
        'selector'   => $selector,
        'token_hash' => $hash,
        'expires'    => date('c', $expiresTs),
        'created_at' => date('c'),
    ];
    $repo->update($userId, ['remember_tokens' => $tokens]);

    $value = $selector . ':' . $validator;
    setcookie(REMEMBER_COOKIE, $value, remember_cookie_options($expiresTs));
}

/** Supprime le cookie remember côté navigateur */
function clear_remember_cookie(): void {
    setcookie(REMEMBER_COOKIE, '', remember_cookie_options(time()-3600));
}

/** Essaie de se connecter via le cookie si pas déjà connecté */
function auto_login_from_cookie_if_needed(): void {
    if (isLoggedIn()) return;
    if (empty($_COOKIE[REMEMBER_COOKIE])) return;

    $raw = (string)$_COOKIE[REMEMBER_COOKIE];
    $parts = explode(':', $raw, 2);
    if (count($parts) !== 2) { clear_remember_cookie(); return; }
    [$selector, $validator] = $parts;
    if ($selector === '' || $validator === '') { clear_remember_cookie(); return; }

    $repo = new Users();
    $candidate = null;
    foreach ($repo->getAll() as $u) {
        foreach (($u['remember_tokens'] ?? []) as $t) {
            if (($t['selector'] ?? '') === $selector) { $candidate = [$u, $t]; break 2; }
        }
    }
    if (!$candidate) { clear_remember_cookie(); return; }

    [$user, $tok] = $candidate;
    $expiresTs = strtotime((string)($tok['expires'] ?? ''));
    if (!$expiresTs || $expiresTs < time()) {
        // jeton expiré: nettoyer
        remove_remember_token((int)$user['id'], $selector);
        clear_remember_cookie();
        return;
    }

    $ok = hash_equals((string)($tok['token_hash'] ?? ''), hash('sha256', $validator));
    if (!$ok) {
        // tentative invalide -> révoquer le sélecteur
        remove_remember_token((int)$user['id'], $selector);
        clear_remember_cookie();
        return;
    }

    // Succès: ouvrir une session + rotation du jeton
    login_user((int)$user['id'], (string)($user['role'] ?? 'user'), (string)($user['pseudo'] ?? ''), $user['avatar'] ?? null);
    remove_remember_token((int)$user['id'], $selector); // one-time
    issue_remember_cookie((int)$user['id']);            // rotate
}

/** Retire un jeton précis côté serveur (par selector) */
function remove_remember_token(int $userId, string $selector): void {
    $repo = new Users();
    $u = $repo->getById($userId);
    if (!$u) return;
    $tokens = array_values(array_filter($u['remember_tokens'] ?? [], function ($t) use ($selector) {
        return ($t['selector'] ?? '') !== $selector;
    }));
    $repo->update($userId, ['remember_tokens' => $tokens]);
}

/** Révoque le jeton stocké dans le cookie courant + supprime le cookie */
function revoke_cookie_token_and_clear(): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $raw = (string)$_COOKIE[REMEMBER_COOKIE];
        $parts = explode(':', $raw, 2);
        if (count($parts) === 2) {
            [$selector, $validator] = $parts;
            if ($selector !== '') {
                // rechercher le user par selector
                $repo = new Users();
                foreach ($repo->getAll() as $u) {
                    foreach (($u['remember_tokens'] ?? []) as $t) {
                        if (($t['selector'] ?? '') === $selector) {
                            remove_remember_token((int)$u['id'], $selector);
                            break 2;
                        }
                    }
                }
            }
        }
    }
    clear_remember_cookie();
}
