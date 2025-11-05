<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/storage/FileUpload.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/config.php';

use SoDrink\Domain\Users;
use SoDrink\Storage\FileUpload;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\require_login;

require_login();
require_csrf();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    json_error('Méthode non autorisée', 405);
}

if (!isset($_FILES['banner'])) {
    json_error('Fichier manquant (banner)', 400);
}

try {
    $info = FileUpload::fromImage(
        $_FILES['banner'],
        BANNERS_PATH,
        defined('MAX_UPLOAD_MB_RUNTIME') ? MAX_UPLOAD_MB_RUNTIME : MAX_UPLOAD_MB,
        ALLOWED_IMAGE_MIME
    );
} catch (Throwable $e) {
    json_error($e->getMessage(), 400);
}

$repo = new Users();
$meId = (int)$_SESSION['user_id'];
$relative = WEB_BASE . '/uploads/banners/' . $info['filename'];
$repo->update($meId, ['banner' => $relative]);

json_success(['banner' => $relative]);
