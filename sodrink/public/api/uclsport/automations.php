<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/UclSportAutomations.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/utils/secrets.php';

use SoDrink\Domain\UclSportAutomations;
use function SoDrink\Security\require_login;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\safe_text;
use function SoDrink\Security\safe_multiline;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo = new UclSportAutomations();

if ($method === 'GET') {
    require_login();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $data = $repo->forUser($userId);
    json_success(['automations' => $data]);
}

if ($method === 'POST') {
    require_login();
    require_csrf();
    $payload = decode_json_body(true);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $fields = validate_payload($payload, true);
    $fields['user_id'] = $userId;
    $automation = $repo->create($fields);
    json_success(['automation' => $automation], 201);
}

if ($method === 'PUT') {
    require_login();
    require_csrf();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        json_error('Identifiant manquant', 400);
    }
    $existing = $repo->getById($id);
    if (!$existing || (int)($existing['user_id'] ?? 0) !== (int)($_SESSION['user_id'] ?? 0)) {
        json_error('Automatisation introuvable', 404);
    }
    $payload = decode_json_body(true);
    $fields = validate_payload($payload, false);
    $updated = $repo->update($id, $fields);
    if (!$updated) {
        json_error('Mise à jour impossible', 500);
    }
    json_success(['automation' => $updated]);
}

if ($method === 'DELETE') {
    require_login();
    require_csrf();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        json_error('Identifiant manquant', 400);
    }
    $existing = $repo->getById($id);
    if (!$existing || (int)($existing['user_id'] ?? 0) !== (int)($_SESSION['user_id'] ?? 0)) {
        json_error('Automatisation introuvable', 404);
    }
    $repo->delete($id);
    json_success(['deleted' => true]);
}

http_response_code(405);
header('Allow: GET, POST, PUT, DELETE');
json_error('Méthode non autorisée', 405);

function decode_json_body(bool $required = false): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    if (!is_array($data)) {
        if ($required) json_error('Corps JSON invalide', 400);
        return [];
    }
    return $data;
}

function validate_payload(array $input, bool $creating): array
{
    $result = [];

    if (array_key_exists('ucl_username', $input) || $creating) {
        $username = safe_text((string)($input['ucl_username'] ?? ''), 120);
        if ($username === '') {
            json_error('Identifiant UCLouvain requis', 422);
        }
        $result['ucl_username'] = $username;
    }

    if ($creating || array_key_exists('ucl_password', $input) || !empty($input['clear_password'])) {
        $password = (string)($input['ucl_password'] ?? '');
        if ($password === '' && $creating && empty($input['clear_password'])) {
            json_error('Mot de passe requis pour la première sauvegarde', 422);
        }
        if ($password !== '') {
            $result['ucl_password'] = $password;
        } elseif (!empty($input['clear_password'])) {
            $result['ucl_password'] = null;
        }
    }

    if (array_key_exists('sport', $input) || $creating) {
        $sport = safe_text((string)($input['sport'] ?? ''), 120);
        if ($sport === '') {
            json_error('Nom du sport requis', 422);
        }
        $result['sport'] = $sport;
    }

    if (array_key_exists('campus', $input) || $creating) {
        $campus = safe_text((string)($input['campus'] ?? 'Louvain-la-Neuve'), 120);
        $result['campus'] = $campus === '' ? 'Louvain-la-Neuve' : $campus;
    }

    if (array_key_exists('session_date', $input) || $creating) {
        $sessionDate = safe_text((string)($input['session_date'] ?? ''), 5);
        if (!preg_match('/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])$/', $sessionDate)) {
            json_error('Format de date invalide (jj/mm)', 422);
        }
        $result['session_date'] = $sessionDate;
    }

    if (array_key_exists('time_slot', $input) || $creating) {
        $timeSlot = safe_text((string)($input['time_slot'] ?? ''), 11);
        if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]-([01][0-9]|2[0-3]):[0-5][0-9]$/', $timeSlot)) {
            json_error('Format horaire invalide (HH:MM-HH:MM)', 422);
        }
        $result['time_slot'] = $timeSlot;
    }

    if (array_key_exists('weekly', $input)) {
        $result['weekly'] = (bool)$input['weekly'];
    }

    if (array_key_exists('headless', $input)) {
        $result['headless'] = (bool)$input['headless'];
    }

    if (array_key_exists('notes', $input)) {
        $notes = safe_multiline((string)$input['notes'], 200);
        $result['notes'] = $notes !== '' ? $notes : null;
    }

    return $result;
}

