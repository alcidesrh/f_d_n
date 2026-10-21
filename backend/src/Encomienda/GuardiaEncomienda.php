<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Entity\Encomienda;
use App\Entity\Enum\TransicionEncomienda;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * Guard del flujo de la encomienda (ADR-038): bloquea la transición que no
 * cumple `ReglasEncomienda::rechazo()`, con su mensaje y su código. La
 * excepción va en el bloqueo para que `FlujoEncomienda` la lance tal cual.
 */
#[AsEventListener(event: "workflow." . TransicionEncomienda::WORKFLOW . ".guard")]
final class GuardiaEncomienda
{
    public const RECHAZO = "rechazo";

    public function __invoke(GuardEvent $event): void
    {
        /** @var Encomienda $encomienda */
        $encomienda = $event->getSubject();
        $rechazo = ReglasEncomienda::rechazo(TransicionEncomienda::from($event->getTransition()->getName()), $encomienda);
        if ($rechazo !== null) {
            $event->addTransitionBlocker(new TransitionBlocker($rechazo->getMessage(), $rechazo->codigo, [self::RECHAZO => $rechazo]));
        }
    }
}
