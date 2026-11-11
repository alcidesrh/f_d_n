<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Acceso\Autorizacion;
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

    public function __construct(
        private readonly Autorizacion $autorizacion,
    ) {}

    public function __invoke(GuardEvent $event): void
    {
        /** @var Encomienda $encomienda */
        $encomienda = $event->getSubject();
        $t = TransicionEncomienda::from($event->getTransition()->getName());
        // El rol de sistema de quien opera (sin sesión, como en el worker, no lo es).
        $sistema = $t->delSistema() && $this->autorizacion->permisos()->sistema();
        $rechazo = ReglasEncomienda::rechazo($t, $encomienda, $sistema);
        if ($rechazo !== null) {
            $event->addTransitionBlocker(new TransitionBlocker($rechazo->getMessage(), $rechazo->codigo, [self::RECHAZO => $rechazo]));
        }
    }
}
