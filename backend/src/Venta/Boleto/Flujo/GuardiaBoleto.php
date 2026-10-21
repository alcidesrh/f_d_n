<?php

declare(strict_types=1);

namespace App\Venta\Boleto\Flujo;

use App\Entity\BoletoAsiento;
use App\Entity\Enum\TransicionBoleto;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * Guard del flujo del boleto (ADR-037): bloquea la transición que no cumple
 * `ReglasTransicion`, con su mensaje y su código. La excepción va en el
 * bloqueo para que `FlujoBoleto` la lance tal cual (el controlador ya sabe
 * traducirla).
 */
#[AsEventListener(event: "workflow." . TransicionBoleto::WORKFLOW . ".guard")]
final class GuardiaBoleto
{
    public const RECHAZO = "rechazo";

    public function __construct(
        private readonly ClockInterface $reloj,
    ) {}

    public function __invoke(GuardEvent $event): void
    {
        /** @var BoletoAsiento $boleto */
        $boleto = $event->getSubject();
        $rechazo = ReglasTransicion::rechazo(TransicionBoleto::from($event->getTransition()->getName()), $boleto, $this->reloj->now());
        if ($rechazo !== null) {
            $event->addTransitionBlocker(new TransitionBlocker($rechazo->getMessage(), $rechazo->codigo, [self::RECHAZO => $rechazo]));
        }
    }
}
