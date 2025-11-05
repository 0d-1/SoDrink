<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/security/auth.php';

use function SoDrink\Security\require_admin;
use function SoDrink\Security\require_csrf;

$file = $GLOBALS['SODRINK_ROOT'] . '/data/torpille.json';

function now_iso(): string {
    $tz = new DateTimeZone('Europe/Brussels');
    return (new DateTimeImmutable('now', $tz))->format(DATE_ATOM);
}

function read_state(string $file): array {
    if (!is_file($file)) {
        return [
            'first_holder'   => null,
            'current_holder' => null,
            'started_at'     => null,
            'history'        => [],
        ];
    }
    $raw = @file_get_contents($file);
    $j = json_decode((string)$raw, true);
    if (!is_array($j)) {
        return [
            'first_holder'   => null,
            'current_holder' => null,
            'started_at'     => null,
            'history'        => [],
        ];
    }
    // Valeurs par défaut au cas où
    $j['first_holder']   = $j['first_holder']   ?? null;
    $j['current_holder'] = $j['current_holder'] ?? null;
    $j['started_at']     = $j['started_at']     ?? null;
    $j['history']        = is_array($j['history'] ?? null) ? $j['history'] : [];
    return $j;
}

function write_state(string $file, array $state): void {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ok = @file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($ok === false) {
        http_response_code(500);
        json_error("Impossible d'écrire $file");
    }
}

/**
 * Tente de récupérer une petite signature "qui" a fait l'action (facultatif).
 */
function performed_by(): ?string {
    // On fait simple pour rester compatible au projet
    $by = null;
    if (isset($_SESSION['user']['email'])) $by = $_SESSION['user']['email'];
    elseif (isset($_SESSION['user']['username'])) $by = $_SESSION['user']['username'];
    elseif (!empty($_SERVER['REMOTE_USER'])) $by = $_SERVER['REMOTE_USER'];
    return $by;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $state = read_state($file);
    json_success(['state' => $state]);
    exit;
}

if ($method === 'PUT' || $method === 'POST') {
    require_admin();
    require_csrf();

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) json_error('Payload JSON invalide', 400);

    $action = $payload['action'] ?? null;
    $state  = read_state($file);

    // Normalisation douce des chaînes
    $sanitize = function (?string $s): string {
        $s = trim((string)$s);
        // longueur max raisonnable
        if (mb_strlen($s) > 120) $s = mb_substr($s, 0, 120);
        return $s;
    };

    if ($action === 'set_first') {
        $holder = $sanitize($payload['holder'] ?? '');
        $force  = (bool)($payload['force'] ?? false);
        if ($holder === '') json_error('Le nom du premier torpillé est requis', 400);

        // Si déjà démarré et pas "force", on bloque
        if (!empty($state['first_holder']) && !$force) {
            json_error('La torpille a déjà démarré. Utilise "force": true pour réinitialiser.', 409);
        }

        // (Ré)initialisation
        $state['first_holder']   = $holder;
        $state['current_holder'] = $holder;
        $state['started_at']     = now_iso();
        $by = performed_by();

        $state['history'] = [[
            'from' => null,
            'to'   => $holder,
            'at'   => $state['started_at'],
            'by'   => $by,
            'note' => 'Démarrage',
        ]];

        write_state($file, $state);
        json_success(['state' => $state, 'updated' => true]);
        exit;
    }

    if ($action === 'transfer') {
        $to   = $sanitize($payload['to'] ?? '');
        $note = $sanitize($payload['note'] ?? '');
        if ($to === '') json_error('Destinataire requis (to)', 400);

        if (empty($state['current_holder'])) {
            json_error('La torpille n’a pas encore démarré. Utilise d’abord action="set_first".', 409);
        }

        $from = (string)$state['current_holder'];
        if (mb_strtolower($from) === mb_strtolower($to)) {
            json_error('Le détenteur courant et la destination sont identiques', 400);
        }

        $by = performed_by();

        $state['current_holder'] = $to;
        $state['history'][] = [
            'from' => $from,
            'to'   => $to,
            'at'   => now_iso(),
            'by'   => $by,
            'note' => $note !== '' ? $note : null,
        ];

        write_state($file, $state);
        json_success(['state' => $state, 'updated' => true]);
        exit;
    }

    json_error('Action inconnue. Utilise "set_first" ou "transfer".', 400);
}

http_response_code(405);
header('Allow: GET, PUT');
json_error('Méthode non autorisée', 405);
