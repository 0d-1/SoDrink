<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

use function SoDrink\Security\csrf_token;

require_method('GET');
json_success(['csrf_token' => csrf_token()]);
