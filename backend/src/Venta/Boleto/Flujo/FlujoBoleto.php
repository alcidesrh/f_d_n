<?php

declare(strict_types=1);

namespace App\Venta\Boleto\Flujo;

use App\Entity\BoletoAsiento;
use App\Entity\Enum\TransicionBoleto;
use App\Salida\SalidaRechazada;
use App\Venta\Excepcion\VentaRechazada;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\TransitionBlockerList;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * El único camino para cambiar el estado de un boleto (ADR-037): aplica una
 * transición del workflow `boleto_asiento` o dice por qué no se puede.
 *
 * Un rechazo es la `SalidaRechazada` o `VentaRechazada` que ya lanzaban el
 * abordaje y la venta (mismo mensaje, código y estado HTTP), así los
 * controladores no cambian. Quien llama sigue haciendo lo que el workflow no
 * sabe: bloquear la salida, comprobar el permiso y la estación del usuario y
 * lo que se consulta en la base (que el asiento siga libre al revertir).
 */
final class FlujoBoleto
{
    /** Clave del contexto de `apply()` con lo que va al detalle de la bitácora. */
    public const BITACORA = "bitacora";

    public function __construct(
        #[Target(TransicionBoleto::WORKFLOW)]
        private readonly WorkflowInterface $flujo,
    ) {}

    /**
     * @param array<string, mixed> $bitacora datos para la entrada de la bitácora (motivo, destino de la reasignación)
     *
     * @throws SalidaRechazada|VentaRechazada
     */
    public function aplicar(BoletoAsiento $b, TransicionBoleto $t, array $bitacora = []): void
    {
        try {
            $this->flujo->apply($b, $t->value, [self::BITACORA => $bitacora]);
        } catch (NotEnabledTransitionException $e) {
            throw self::rechazoDe($b, $t, $e->getTransitionBlockerList());
        }
    }

    /** @throws SalidaRechazada|VentaRechazada si no se puede */
    public function exigir(BoletoAsiento $b, TransicionBoleto $t): void
    {
        $rechazo = $this->rechazo($b, $t);
        if ($rechazo !== null) {
            throw $rechazo;
        }
    }

    public function puede(BoletoAsiento $b, TransicionBoleto $t): bool
    {
        return $this->flujo->can($b, $t->value);
    }

    /** Por qué no se puede, o null si se puede. */
    public function rechazo(BoletoAsiento $b, TransicionBoleto $t): SalidaRechazada|VentaRechazada|null
    {
        $bloqueos = $this->flujo->buildTransitionBlockerList($b, $t->value);

        return $bloqueos->isEmpty() ? null : self::rechazoDe($b, $t, $bloqueos);
    }

    /**
     * Las acciones del panel del listado de boletos (ADR-033) que son
     * transiciones: null si el boleto la admite ahora, o el motivo. El
     * permiso de quien mira no entra (lo trae `GET /api/boletos/opciones`).
     *
     * @return array{chequear: ?string, cancelar: ?string, revertir: ?string, anular: ?string, reasignar: ?string}
     */
    public function acciones(BoletoAsiento $b): array
    {
        $motivo = fn(TransicionBoleto $t) => $this->rechazo($b, $t)?->getMessage();

        return [
            "chequear" => $motivo(TransicionBoleto::alChequear($b->getSalida()->getEstado())),
            "cancelar" => $motivo(TransicionBoleto::CANCELAR),
            "revertir" => $motivo(TransicionBoleto::REVERTIR),
            "anular" => $motivo(TransicionBoleto::ANULAR),
            "reasignar" => $motivo(TransicionBoleto::REASIGNAR),
        ];
    }

    /**
     * El primer bloqueo: el de un guard trae su excepción; el del estado
     * del boleto (la máquina no tiene esa transición desde ahí) se arma con
     * lo que la transición exige.
     */
    private static function rechazoDe(BoletoAsiento $b, TransicionBoleto $t, TransitionBlockerList $bloqueos): SalidaRechazada|VentaRechazada
    {
        foreach ($bloqueos as $bloqueo) {
            $rechazo = $bloqueo->getParameters()[GuardiaBoleto::RECHAZO] ?? null;
            if ($rechazo instanceof SalidaRechazada || $rechazo instanceof VentaRechazada) {
                return $rechazo;
            }
            if ($bloqueo->getCode() !== TransitionBlocker::BLOCKED_BY_MARKING) {
                return ReglasTransicion::rechazar($t, $bloqueo->getMessage(), "transicion_bloqueada", 409);
            }
        }

        return ReglasTransicion::rechazar(
            $t,
            sprintf("El boleto %d (asiento %s) está %s: %s.", $b->getId(), $b->getAsiento()?->getNumero(), mb_strtolower($b->getEstado()->etiqueta()), $t->requisito()),
            $t->codigoEstado(),
            $t->deLaVenta() ? 422 : 409,
        );
    }
}
