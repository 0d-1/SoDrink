<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';

use SoDrink\Domain\Users;

$repo = new Users();
$id = (int)($_GET['id'] ?? 0);
$uParam = trim((string)($_GET['u'] ?? ''));

$u = null;
if ($id > 0) {
    $u = $repo->getById($id);
} elseif ($uParam !== '') {
    $u = $repo->findByPseudo($uParam);
}

if (!$u) json_error('Utilisateur introuvable', 404);
json_success(['user' => Users::toPublic($u)]);
