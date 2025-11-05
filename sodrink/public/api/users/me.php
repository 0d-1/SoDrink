<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/NotificationPreferences.php';

use SoDrink\Domain\Users;
use SoDrink\Domain\NotificationPreferences;
use function SoDrink\Security\require_login;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\safe_multiline;
use function SoDrink\Security\safe_text;
use function SoDrink\Security\valid_pseudo;
use function SoDrink\Security\valid_name;
use function SoDrink\Security\sanitize_instagram;
use function SoDrink\Security\sanitize_url;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    require_login();
    $repo = new Users();
    $me = $repo->getById((int)($_SESSION['user_id'] ?? 0));
    if (!$me) json_error('Utilisateur introuvable', 404);
    $user = Users::toPublic($me);
    $user['notification_settings'] = NotificationPreferences::normalize($me['notification_settings'] ?? null);
    json_success(['user' => $user]);
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

    if (array_key_exists('bio', $data)) {
        $fields['bio'] = safe_multiline((string)$data['bio'], 600);
    }

    if (array_key_exists('website', $data)) {
        $fields['website'] = sanitize_url($data['website']);
    }

    if (array_key_exists('location', $data)) {
        $loc = safe_text((string)$data['location'], 80);
        $fields['location'] = $loc !== '' ? $loc : null;
    }

    if (array_key_exists('relationship_status', $data)) {
        $allowed = ['single','relationship','married','complicated','hidden'];
        $status = (string)$data['relationship_status'];
        if ($status === '' || $status === 'none') {
            $fields['relationship_status'] = null;
        } elseif (!in_array($status, $allowed, true)) {
            json_error('Statut relationnel invalide', 422);
        } else {
            $fields['relationship_status'] = $status;
        }
    }

    if (array_key_exists('links', $data)) {
        if (!is_array($data['links'])) {
            json_error('Liens invalides', 422);
        }
        $links = [];
        foreach ($data['links'] as $link) {
            if (!is_array($link)) continue;
            $label = safe_text((string)($link['label'] ?? ''), 40);
            $urlRaw = $link['url'] ?? '';
            if ($label === '' && trim((string)$urlRaw) === '') {
                continue;
            }
            $url = sanitize_url((string)$urlRaw);
            if ($label === '' || !$url) {
                json_error('Lien invalide', 422);
            }
            $links[] = ['label' => $label, 'url' => $url];
            if (count($links) >= 5) {
                break;
            }
        }
        $fields['links'] = $links;
    }

    if (array_key_exists('email', $data)) {
        $email = trim((string)$data['email']);
        if ($email === '') {
            $fields['email'] = null;
        } else {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_error('Email invalide', 422);
            }
            $fields['email'] = mb_strtolower($email);
        }
    }

    if (array_key_exists('password', $data)) {
        $new = (string)$data['password'];
        if (mb_strlen($new) < 8) json_error('Mot de passe trop court (>=8)', 422);
        $fields['pass_hash'] = password_hash($new, PASSWORD_DEFAULT);
    }

    if (array_key_exists('notification_settings', $data)) {
        if (!is_array($data['notification_settings'])) {
            json_error('Paramètres de notification invalides', 422);
        }
        $fields['notification_settings'] = NotificationPreferences::normalize($data['notification_settings']);
    }

    if (!$fields) json_error('Aucun champ à mettre à jour', 400);

    $repo->update($meId, $fields);
    $updated = $repo->getById($meId);
    $user = Users::toPublic($updated);
    $user['notification_settings'] = NotificationPreferences::normalize($updated['notification_settings'] ?? null);
    json_success(['user' => $user]);
}

http_response_code(405);
header('Allow: GET, PUT');
json_error('Méthode non autorisée', 405);
