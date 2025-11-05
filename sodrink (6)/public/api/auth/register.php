<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';

use SoDrink\Domain\Users;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\safe_text;
use function SoDrink\Security\valid_pseudo;
use function SoDrink\Security\valid_name;
use function SoDrink\Security\valid_password;
use function SoDrink\Security\sanitize_instagram;
use function SoDrink\Security\login_user;

require_method('POST');
require_csrf();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) json_error('Corps JSON invalide', 400);

$pseudo = safe_text($data['pseudo'] ?? '', 20);
$prenom = safe_text($data['prenom'] ?? '', 40);
$nom    = safe_text($data['nom'] ?? '', 40);
$pass   = (string)($data['password'] ?? '');
$insta  = sanitize_instagram($data['instagram'] ?? null);

if (!valid_pseudo($pseudo))  json_error('Pseudo invalide (3–20, alphanum + _.-)', 422);
if (!valid_name($prenom))    json_error('Prénom invalide', 422);
if (!valid_name($nom))       json_error('Nom invalide', 422);
if (!valid_password($pass))  json_error('Mot de passe trop court (>=8)', 422);

$repo = new Users();
if ($repo->findByPseudo($pseudo)) json_error('Ce pseudo est déjà utilisé', 409);

$hash = password_hash($pass, PASSWORD_DEFAULT);
$user = $repo->create([
    'pseudo'    => $pseudo,
    'prenom'    => $prenom,
    'nom'       => $nom,
    'pass_hash' => $hash,
    'instagram' => $insta,
]);

login_user((int)$user['id'], $user['role'] ?? 'user', $user['pseudo']);
json_success(['user' => Users::toPublic($user)], 201);
