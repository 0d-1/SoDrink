<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

use function SoDrink\Security\require_csrf;
use function SoDrink\Security\logout_user;
use function SoDrink\Security\revoke_cookie_token_and_clear;

require_method('POST');
require_csrf();

// Révoque le jeton "remember" de cet appareil + supprime le cookie
revoke_cookie_token_and_clear();

logout_user();
json_success(['message' => 'Déconnecté']);
