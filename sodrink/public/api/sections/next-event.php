<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Events.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Notifications.php';

use SoDrink\Domain\Events;
use SoDrink\Domain\Users;
use SoDrink\Domain\Notifications;
use function SoDrink\Security\require_login;
use function SoDrink\Security\isAdmin;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\safe_text;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo   = new Events();
$users  = new Users();

/**
 * GET  -> retourne la prochaine soirée + la liste, avec:
 *         - author (id, pseudo)
 *         - participants_count
 */
if ($method === 'GET') {
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 6)));

    $next = $repo->nextUpcoming();
    $list = $repo->listUpcoming($limit);

    // ⚠️ C'est ICI qu'on enrichit avec le nombre de participants
    $enrich = function ($ev) use ($users) {
        if (!$ev) return null;
        $au = $users->getById((int)($ev['created_by'] ?? 0));
        $ev['author'] = $au ? ['id' => (int)$au['id'], 'pseudo' => $au['pseudo']] : null;
        $ev['participants_count'] = count($ev['participants'] ?? []);
        return $ev;
    };

    $next = $enrich($next);
    $list = array_map($enrich, $list);

    json_success(['next' => $next, 'upcoming' => $list]);
}

/**
 * POST -> créer un évènement (tout utilisateur connecté)
 */
if ($method === 'POST') {
    require_login();
    require_csrf();

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) json_error('Corps JSON invalide', 400);

    $date = safe_text($data['date'] ?? '', 10);
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_error('Date invalide (YYYY-MM-DD)', 422);

    $max = null;
    if (isset($data['max_participants'])) {
        $max = is_numeric($data['max_participants']) ? (int)$data['max_participants'] : 0;
        $max = max(0, min(500, $max));
        if ($max === 0) {
            $max = null;
        }
    }

    $event = $repo->create([
        'date'        => $date,
        'lieu'        => safe_text($data['lieu'] ?? '', 120),
        'theme'       => safe_text($data['theme'] ?? '', 120),
        'description' => safe_text($data['description'] ?? '', 800),
        'created_by'  => (int)($_SESSION['user_id'] ?? 0),
        'max_participants' => $max,
    ]);

    // Notification à l'auteur (lui-même) pour confirmation
    (new Notifications())->send(
        (int)$event['created_by'],
        'event_created',
        "Votre soirée du {$event['date']} est publiée.",
        WEB_BASE . '/#section-next-event'
    );

    json_success(['event' => $event], 201);
}

/**
 * PUT / DELETE -> modifier / supprimer (seulement auteur ou admin)
 */
if ($method === 'PUT' || $method === 'DELETE') {
    require_login();
    require_csrf();

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_error('Paramètre id requis', 400);

    $ev = $repo->getById($id);
    if (!$ev) json_error('Événement introuvable', 404);

    $me    = (int)($_SESSION['user_id'] ?? 0);
    $admin = isAdmin();
    if (!$admin && (int)($ev['created_by'] ?? -1) !== $me) {
        json_error('Vous ne pouvez modifier que vos propres événements', 403);
    }

    if ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('Corps JSON invalide', 400);

        $fields = [];
        if (isset($data['date'])) {
            $d = safe_text((string)$data['date'], 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) json_error('Date invalide', 422);
            $fields['date'] = $d;
        }
        foreach (['lieu'=>120,'theme'=>120,'description'=>800] as $k=>$len) {
            if (isset($data[$k])) $fields[$k] = safe_text((string)$data[$k], $len);
        }
        if (array_key_exists('max_participants', $data)) {
            $mp = $data['max_participants'];
            if ($mp === null || $mp === '' || (is_string($mp) && trim($mp) === '')) {
                $fields['max_participants'] = null;
            } else {
                $val = is_numeric($mp) ? (int)$mp : 0;
                $val = max(0, min(500, $val));
                $fields['max_participants'] = $val === 0 ? null : $val;
            }
        }
        if (!$fields) json_error('Aucune mise à jour', 400);

        $repo->update($id, $fields);

        (new Notifications())->send(
            (int)$ev['created_by'],
            'event_updated',
            "Votre soirée du {$ev['date']} a été modifiée.",
            WEB_BASE . '/#section-next-event'
        );

        json_success(['updated' => true]);
    } else { // DELETE
        $repo->delete($id);

        (new Notifications())->send(
            (int)$ev['created_by'],
            'event_deleted',
            "Votre soirée du {$ev['date']} a été supprimée.",
            WEB_BASE . '/#section-next-event'
        );

        json_success(['deleted' => true]);
    }
}

// Méthode non prise en charge
http_response_code(405);
header('Allow: GET, POST, PUT, DELETE');
json_error('Méthode non autorisée', 405);
