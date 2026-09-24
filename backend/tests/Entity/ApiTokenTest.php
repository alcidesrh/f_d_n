<?php

namespace App\Tests\Entity;

use App\Entity\ApiToken;
use PHPUnit\Framework\TestCase;

final class ApiTokenTest extends TestCase
{
    public function testGeneraUnSecretoConPrefijo(): void
    {
        $this->assertMatchesRegularExpression('/^fdn_[0-9a-f]{64}$/', (new ApiToken())->getToken());
    }

    public function testEsValidoSoloSiEstaActivoYNoVencido(): void
    {
        $token = new ApiToken();
        $this->assertFalse($token->isValid(), 'sin activar');

        $token->setActivo(true);
        $this->assertTrue($token->isValid(), 'activo sin vencimiento');

        $token->setExpira(new \DateTime('+1 day'));
        $this->assertTrue($token->isValid(), 'activo y vigente');

        $token->setExpira(new \DateTime('-1 minute'));
        $this->assertFalse($token->isValid(), 'vencido');

        $token->setExpira(null)->setActivo(false);
        $this->assertFalse($token->isValid(), 'revocado');
    }
}
