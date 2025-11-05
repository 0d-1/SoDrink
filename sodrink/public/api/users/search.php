<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';

use SoDrink\Domain\Users;

$q = trim((string)($_GET['q'] ?? ''));
$limit = 8;
$repo = new Users();

if ($q === '') { json_success(['users' => []]); }

$all = $repo->getAll();
$qn = mb_strtolower($q);
$matched = [];
foreach ($all as $u) {
    $hay = mb_strtolower(($u['pseudo'] ?? '') . ' ' . ($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
    if (mb_strpos($hay, $qn) !== false) {
        $matched[] = [
            'id' => (int)$u['id'],
            'pseudo' => $u['pseudo'],
            'prenom' => $u['prenom'] ?? '',
            'nom' => $u['nom'] ?? '',
            'avatar' => $u['avatar'] ?? null,
        ];
        if (count($matched) >= $limit) break;
    }
}
json_success(['users' => $matched]);
