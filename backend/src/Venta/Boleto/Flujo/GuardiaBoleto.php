<?php

declare(strict_types=1);

namespace App\Venta\Boleto\Flujo;

use App\Acceso\Autorizacion;
use App\Autorizacion\DispensasEnCurso;
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
 * traducirla). Un boleto que la operación en curso anula o reasigna con una
 * autorización fuera de hora (`DispensasEnCurso`, ADR-040) no mira la hora;
 * con una fuera de estado (ADR-058), se opera como lo haría el rol de sistema.
 */
#[AsEventListener(event: "workflow." . TransicionBoleto::WORKFLOW . ".guard")]
final class GuardiaBoleto
{
    public const RECHAZO = "rechazo";

    public function __construct(
        private readonly ClockInterface $reloj,
        private readonly DispensasEnCurso $dispensas,
        private readonly Autorizacion $autorizacion,
    ) {}

    public function __invoke(GuardEvent $event): void
    {
        /** @var BoletoAsiento $boleto */
        $boleto = $event->getSubject();
        $t = TransicionBoleto::from($event->getTransition()->getName());
        // El rol de sistema de quien opera (sin sesión, como en el worker, no lo es), o una
        // autorización fuera de estado para anular o reasignar este boleto (ADR-058).
        $sistema = $t->delSistema() && (
            $this->autorizacion->permisos()->sistema()
            || (in_array($t, [TransicionBoleto::ANULAR, TransicionBoleto::REASIGNAR], true) && $this->dispensas->fueraDeEstado($boleto))
        );
        $rechazo = ReglasTransicion::rechazo($t, $boleto, $this->reloj->now(), $this->dispensas->fueraDeHora($boleto), $sistema);
        if ($rechazo !== null) {
            $event->addTransitionBlocker(new TransitionBlocker($rechazo->getMessage(), $rechazo->codigo, [self::RECHAZO => $rechazo]));
        }
    }
}
