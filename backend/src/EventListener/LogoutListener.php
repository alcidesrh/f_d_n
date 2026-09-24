<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Cierre de sesión (`POST /api/logout`): desactiva el Bearer usado en la
 * petición, para que no siga sirviendo tras el logout, y responde JSON en
 * lugar de redirigir.
 */
#[AsEventListener]
final class LogoutListener
{
    public function __construct(
        private readonly ApiTokenRepository $tokens,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $bearer = $event->getRequest()->headers->get('Authorization', '');
        if (str_starts_with($bearer, 'Bearer ')) {
            $token = $this->tokens->findOneBy(['token' => substr($bearer, 7)]);
            if ($token !== null) {
                $token->setActivo(false);
                $this->entityManager->flush();
            }
        }
        $event->setResponse(new JsonResponse(['logout' => true]));
    }
}
