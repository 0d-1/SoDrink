<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';

use SoDrink\Domain\Users;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\safe_text;
use function SoDrink\Security\login_user;
use function SoDrink\Security\issue_remember_cookie;

require_method('POST');
require_csrf();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) json_error('Corps JSON invalide', 400);

$pseudo = safe_text($data['pseudo'] ?? '', 20);
$pass   = (string)($data['password'] ?? '');

// "remember" peut être '1','true','on','yes'
$remember = false;
if (isset($data['remember'])) {
    $val = strtolower(trim((string)$data['remember']));
    $remember = in_array($val, ['1','true','on','yes'], true);
}

$repo = new Users();
$u = $repo->findByPseudo($pseudo);
if (!$u || !password_verify($pass, (string)$u['pass_hash'])) {
    usleep(200000);
    json_error('Identifiants incorrects', 401);
}

login_user((int)$u['id'], $u['role'] ?? 'user', $u['pseudo'], $u['avatar'] ?? null);

if ($remember) {
    issue_remember_cookie((int)$u['id']);
}

json_success(['user' => Users::toPublic($u)]);
