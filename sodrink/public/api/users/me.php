<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';

use SoDrink\Domain\Users;
use function SoDrink\Security\require_login;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\safe_text;
use function SoDrink\Security\valid_pseudo;
use function SoDrink\Security\valid_name;
use function SoDrink\Security\sanitize_instagram;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    require_login();
    $repo = new Users();
    $me = $repo->getById((int)($_SESSION['user_id'] ?? 0));
    if (!$me) json_error('Utilisateur introuvable', 404);
    json_success(['user' => Users::toPublic($me)]);
}

if ($method === 'PUT') {
    require_login();
    require_csrf();

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) json_error('Corps JSON invalide', 400);

    $repo = new Users();
    $meId = (int)$_SESSION['user_id'];
    $me = $repo->getById($meId);
    if (!$me) json_error('Utilisateur introuvable', 404);

    $fields = [];

    if (array_key_exists('pseudo', $data)) {
        $pseudo = safe_text((string)$data['pseudo'], 20);
        if (!valid_pseudo($pseudo)) json_error('Pseudo invalide', 422);
        $existing = $repo->findByPseudo($pseudo);
        if ($existing && (int)$existing['id'] !== $meId) json_error('Ce pseudo est déjà pris', 409);
        $fields['pseudo'] = $pseudo;
        $_SESSION['pseudo'] = $pseudo;
    }

    if (array_key_exists('prenom', $data)) {
        $prenom = safe_text((string)$data['prenom'], 40);
        if (!valid_name($prenom)) json_error('Prénom invalide', 422);
        $fields['prenom'] = $prenom;
    }

    if (array_key_exists('nom', $data)) {
        $nom = safe_text((string)$data['nom'], 40);
        if (!valid_name($nom)) json_error('Nom invalide', 422);
        $fields['nom'] = $nom;
    }

    if (array_key_exists('instagram', $data)) {
        $insta = sanitize_instagram($data['instagram']);
        $fields['instagram'] = $insta;
    }

    if (array_key_exists('password', $data)) {
        $new = (string)$data['password'];
        if (mb_strlen($new) < 8) json_error('Mot de passe trop court (>=8)', 422);
        $fields['pass_hash'] = password_hash($new, PASSWORD_DEFAULT);
    }

    if (!$fields) json_error('Aucun champ à mettre à jour', 400);

    $repo->update($meId, $fields);
    $updated = $repo->getById($meId);
    json_success(['user' => Users::toPublic($updated)]);
}

http_response_code(405);
header('Allow: GET, PUT');
json_error('Méthode non autorisée', 405);
