<?php

namespace App\Tests\Security;

use App\Entity\ApiToken;
use App\EventListener\LogoutListener;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutListenerTest extends TestCase
{
    public function testRevocaElBearerDeLaPeticionYRespondeJson(): void
    {
        $token = (new ApiToken())->setActivo(true);
        $tokens = $this->createMock(ApiTokenRepository::class);
        $tokens->expects($this->once())->method('findOneBy')->with(['token' => 'fdn_abc'])->willReturn($token);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $event = new LogoutEvent(new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer fdn_abc']), null);
        (new LogoutListener($tokens, $em))($event);

        $this->assertFalse($token->isActivo());
        $this->assertInstanceOf(JsonResponse::class, $event->getResponse());
        $this->assertSame('{"logout":true}', $event->getResponse()->getContent());
    }

    public function testSinBearerSoloResponde(): void
    {
        $tokens = $this->createMock(ApiTokenRepository::class);
        $tokens->expects($this->never())->method('findOneBy');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $event = new LogoutEvent(new Request(), null);
        (new LogoutListener($tokens, $em))($event);

        $this->assertSame(200, $event->getResponse()->getStatusCode());
    }
}
