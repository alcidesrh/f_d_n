<?php

namespace App\Services\Factories;

use App\Entity\Reservacion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ReservacionFactory {

    public function __construct(private RequestStack $requestStack, private EntityManagerInterface $entityManagerInterface) {
    }

    public function __invoke(): ?Reservacion {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        if ($session && $session->has('reservacion')) {
            // $this->entityManagerInterface->flush();
            if ($reservacion = $this->entityManagerInterface->find(Reservacion::class, $session->get('reservacion'))) {

                return $reservacion;
            }
        }

        if ($session = $this->requestStack->getSession()) {
            return new Reservacion($session->get('_locale'));
        } else {
            return new Reservacion();
        }
    }
}
