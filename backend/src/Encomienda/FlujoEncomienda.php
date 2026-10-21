<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Bitacora\RegistradorBitacora;
use App\Entity\Encomienda;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\TransicionEncomienda;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\TransitionBlockerList;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * El único camino para cambiar el estado de una encomienda (ADR-038): aplica
 * una transición del workflow `encomienda` o dice por qué no se puede, con la
 * `EncomiendaRechazada` de siempre. Quien llama sigue haciendo lo que el
 * workflow no sabe: bloquear la fila, la salida en que se embarca, la
 * estación donde se baja y quién opera.
 */
final class FlujoEncomienda
{
    public function __construct(
        #[Target(TransicionEncomienda::WORKFLOW)]
        private readonly WorkflowInterface $flujo,
    ) {}

    /**
     * @param array<string, mixed> $bitacora datos para la entrada de la bitácora (estación, motivo, quién retira)
     *
     * @throws EncomiendaRechazada
     */
    public function aplicar(Encomienda $e, TransicionEncomienda $t, array $bitacora = []): void
    {
        try {
            $this->flujo->apply($e, $t->value, [RegistradorBitacora::CONTEXTO => $bitacora]);
        } catch (NotEnabledTransitionException $x) {
            throw self::rechazoDe($e, $t, $x->getTransitionBlockerList());
        }
    }

    /** @throws EncomiendaRechazada si no se puede */
    public function exigir(Encomienda $e, TransicionEncomienda $t): void
    {
        $rechazo = $this->rechazo($e, $t);
        if ($rechazo !== null) {
            throw $rechazo;
        }
    }

    public function puede(Encomienda $e, TransicionEncomienda $t): bool
    {
        return $this->flujo->can($e, $t->value);
    }

    /** Por qué no se puede, o null si se puede. */
    public function rechazo(Encomienda $e, TransicionEncomienda $t): ?EncomiendaRechazada
    {
        $bloqueos = $this->flujo->buildTransitionBlockerList($e, $t->value);

        return $bloqueos->isEmpty() ? null : self::rechazoDe($e, $t, $bloqueos);
    }

    /**
     * Entregarla: desembarcada en su destino o, como en el legado, en
     * tránsito en una salida que pasa por su destino (el bus llegó y nadie la
     * bajó en el sistema: se desembarca al entregarla).
     */
    public function rechazoEntrega(Encomienda $e): ?EncomiendaRechazada
    {
        if ($e->getEstado() !== EstadoEncomienda::TRANSITO) {
            return $this->rechazo($e, TransicionEncomienda::ENTREGAR);
        }
        $llega = $e->getSalida() !== null && in_array($e->getDestino()?->getId(), RutasEncomienda::idsDeSalida($e->getSalida()), true);

        return $llega ? null : new EncomiendaRechazada("La salida en que va no pasa por su destino: se entrega después del transbordo.", "estado");
    }

    /**
     * Las acciones del listado de encomiendas que son transiciones: null si
     * la encomienda la admite ahora, o el motivo. El permiso y la estación de
     * quien mira no entran (el frontend los suma; el backend los vuelve a
     * exigir al operar).
     *
     * @return array{embarcar: ?string, desembarcar: ?string, entregar: ?string, anular: ?string}
     */
    public function acciones(Encomienda $e): array
    {
        return [
            "embarcar" => $this->rechazo($e, TransicionEncomienda::EMBARCAR)?->getMessage(),
            "desembarcar" => $this->rechazo($e, TransicionEncomienda::DESEMBARCAR)?->getMessage(),
            "entregar" => $this->rechazoEntrega($e)?->getMessage(),
            "anular" => $this->rechazo($e, TransicionEncomienda::ANULAR)?->getMessage(),
        ];
    }

    /**
     * El primer bloqueo: el de un guard trae su excepción; el del estado (la
     * máquina no tiene esa transición desde ahí) se arma con lo que la
     * transición exige.
     */
    private static function rechazoDe(Encomienda $e, TransicionEncomienda $t, TransitionBlockerList $bloqueos): EncomiendaRechazada
    {
        foreach ($bloqueos as $bloqueo) {
            $rechazo = $bloqueo->getParameters()[GuardiaEncomienda::RECHAZO] ?? null;
            if ($rechazo instanceof EncomiendaRechazada) {
                return $rechazo;
            }
            if ($bloqueo->getCode() !== TransitionBlocker::BLOCKED_BY_MARKING) {
                return new EncomiendaRechazada($bloqueo->getMessage(), "transicion_bloqueada", 409);
            }
        }

        return new EncomiendaRechazada(sprintf("La encomienda está %s: %s.", mb_strtolower($e->getEstado()->etiqueta()), $t->requisito()), "estado");
    }
}
