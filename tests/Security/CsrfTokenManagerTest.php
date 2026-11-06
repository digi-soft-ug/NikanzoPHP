<?php

declare(strict_types=1);

namespace Nikanzo\Tests\Security;

use Nikanzo\Core\Security\CsrfTokenManager;
use PHPUnit\Framework\TestCase;

final class CsrfTokenManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testEmptyStoredTokenNeverValidatesEmptySubmission(): void
    {
        $this->assertFalse((new CsrfTokenManager())->isValid(''));
    }

    public function testIssuedTokenValidatesAndWrongOneDoesNot(): void
    {
        $m = new CsrfTokenManager();
        $t = $m->getToken();
        $this->assertTrue($m->isValid($t));
        $this->assertFalse($m->isValid($t . 'x'));
    }
}
