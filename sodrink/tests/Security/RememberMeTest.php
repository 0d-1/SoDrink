<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/security/auth.php';

class RememberMeTest extends TestCase
{
    public function testRememberCookieExpirationIsFourteenDays(): void
    {
        $now = 1_000_000;
        $expected = $now + 14 * 24 * 3600;
        self::assertSame($expected, \SoDrink\Security\remember_cookie_expiration_time($now));
    }
}
