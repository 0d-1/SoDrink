<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';

use SoDrink\Domain\Users;
use function SoDrink\Security\require_admin;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\safe_text;
use function SoDrink\Security\valid_pseudo;
use function SoDrink\Security\valid_name;

require_admin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo = new Users();

if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $u = $repo->getById($id);
        if (!$u) json_error('Utilisateur introuvable', 404);
        json_success(['user' => Users::toPublic($u)]);
    }
    $list = array_map([Users::class,'toPublic'], $repo->getAll());
    json_success(['users' => $list]);
}

if ($method === 'POST') {
    require_csrf();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) json_error('Corps JSON invalide', 400);

    $pseudo = safe_text($data['pseudo'] ?? '', 20);
    $prenom = safe_text($data['prenom'] ?? '', 40);
    $nom    = safe_text($data['nom'] ?? '', 40);
    $role   = in_array(($data['role'] ?? 'user'), ['user','admin'], true) ? $data['role'] : 'user';
    $pass   = (string)($data['password'] ?? '');

    if (!valid_pseudo($pseudo)) json_error('Pseudo invalide', 422);
    if (!valid_name($prenom))  json_error('Prénom invalide', 422);
    if (!valid_name($nom))     json_error('Nom invalide', 422);
    if (mb_strlen($pass) < 8)  json_error('Mot de passe trop court', 422);
    if ($repo->findByPseudo($pseudo)) json_error('Pseudo déjà pris', 409);

    $u = $repo->create([
        'pseudo'    => $pseudo,
        'prenom'    => $prenom,
        'nom'       => $nom,
        'instagram' => $data['instagram'] ?? null,
        'pass_hash' => password_hash($pass, PASSWORD_DEFAULT),
        'role'      => $role,
    ]);
    json_success(['user' => Users::toPublic($u)], 201);
}

if ($method === 'PUT') {
    require_csrf();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_error('Paramètre id requis', 400);
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) json_error('Corps JSON invalide', 400);

    $fields = [];
    if (isset($data['pseudo'])) {
        $p = safe_text((string)$data['pseudo'], 20);
        if (!valid_pseudo($p)) json_error('Pseudo invalide', 422);
        $existing = $repo->findByPseudo($p);
        if ($existing && (int)$existing['id'] !== $id) json_error('Pseudo déjà pris', 409);
        $fields['pseudo'] = $p;
    }
    if (isset($data['prenom'])) { $v = safe_text((string)$data['prenom'], 40); if (!valid_name($v)) json_error('Prénom invalide', 422); $fields['prenom'] = $v; }
    if (isset($data['nom']))    { $v = safe_text((string)$data['nom'], 40);    if (!valid_name($v))  json_error('Nom invalide', 422);     $fields['nom']    = $v; }
    if (isset($data['instagram'])) $fields['instagram'] = $data['instagram'] ?: null;
    if (isset($data['password'])) {
        $pw = (string)$data['password']; if (mb_strlen($pw) < 8) json_error('Mot de passe trop court', 422);
        $fields['pass_hash'] = password_hash($pw, PASSWORD_DEFAULT);
    }
    if (isset($data['role'])) {
        $role = in_array($data['role'], ['user','admin'], true) ? $data['role'] : 'user';
        $fields['role'] = $role;
    }
    if (!$fields) json_error('Aucun champ à mettre à jour', 400);

    $ok = $repo->update($id, $fields);
    if (!$ok) json_error('Utilisateur introuvable', 404);
    json_success(['updated' => true]);
}

if ($method === 'DELETE') {
    require_csrf();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_error('Paramètre id requis', 400);
    if ((int)($_SESSION['user_id'] ?? 0) === $id) json_error('Impossible de supprimer votre propre compte', 400);
    $ok = $repo->delete($id);
    if (!$ok) json_error('Utilisateur introuvable', 404);
    json_success(['deleted' => true]);
}

http_response_code(405);
header('Allow: GET, POST, PUT, DELETE');
json_error('Méthode non autorisée', 405);
