<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use App\Entity\Trayecto;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Editar, anular y eliminar salidas (ADR-024), cada operación con la opción
 * de propagarse a las salidas futuras idénticas (`FirmaSalida`: mismo
 * trayecto, bus, empresa y hora; solo cambia el día) que siguen programadas.
 *
 * Una salida con asientos comprometidos (`AsientosVendidos`) nunca se toca:
 * se devuelve en `omitidas` para que el usuario reasigne o anule esos
 * asientos a mano. Tampoco se mueve una salida a una hora en que el bus está
 * en otro viaje (`conflicto`), ni se borra una con historial de boletos
 * (`historial`: se anula). Las salidas afectadas se bloquean (como en la venta)
 * para que no se venda un asiento entre la comprobación y el cambio.
 */
final class GestionSalidas
{
    public const ASIENTOS = "asientos";
    public const CONFLICTO = "conflicto";
    public const HISTORIAL = "historial";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AsientosVendidos $vendidos,
        private readonly AgendaFlota $agendas,
        private readonly Esquemas $esquemas,
        private readonly Transaccion $transaccion,
    ) {}

    /**
     * Para el diálogo de confirmación: cuántas futuras idénticas hay y
     * cuántas de ellas (y si la propia salida) tienen asientos comprometidos.
     *
     * @return array<string, mixed>
     */
    public function propagacion(int $id): array
    {
        $salida = $this->salida($id);
        $identicas = $this->identicas($salida);
        $vendidos = $this->vendidos->porSalida(array_map(static fn(Salida $s) => (int) $s->getId(), [$salida, ...$identicas]));

        return [
            "salida" => VistaSalida::referencia($salida),
            "vendidos" => $vendidos[$salida->getId()] ?? 0,
            "identicas" => count($identicas),
            "identicasConAsientos" => count(array_filter($identicas, static fn(Salida $s) => isset($vendidos[$s->getId()]))),
            "ultima" => $identicas === [] ? null : end($identicas)->getFecha()->format(DATE_ATOM),
        ];
    }

    /**
     * `$datos`: `{ trayectoId?, busId?, fecha?: AAAA-MM-DDTHH:MM }`. La salida
     * toma la fecha completa; las idénticas conservan su día y toman la hora.
     *
     * @param array<string, mixed> $datos
     *
     * @return array{aplicadas: list<array<string, mixed>>, omitidas: list<array<string, mixed>>}
     */
    public function editar(int $id, array $datos, bool $propagar): array
    {
        return $this->transaccion->ejecutar(function () use ($id, $datos, $propagar): array {
            [$salida, $objetivos] = $this->bloquear($id, $propagar);
            $this->exigirProgramada($salida, "editar");

            $trayecto = isset($datos["trayectoId"])
                ? ($this->em->find(Trayecto::class, (int) $datos["trayectoId"]) ?? throw new SalidaRechazada("El trayecto no existe.", "trayecto_inexistente", 404))
                : $salida->getTrayecto();
            if ($trayecto !== $salida->getTrayecto() && $trayecto->getActivo() === false) {
                throw new SalidaRechazada("El trayecto " . VistaSalida::ruta($trayecto) . " está inactivo.", "trayecto_inactivo");
            }
            $bus = isset($datos["busId"]) ? $this->esquemas->buses([(int) $datos["busId"]])[(int) $datos["busId"]] : $salida->getBus();
            $fecha = isset($datos["fecha"]) ? self::fecha($datos["fecha"]) : $salida->getFecha();
            if ($fecha != $salida->getFecha() && $fecha < new \DateTime()) {
                throw new SalidaRechazada("No se puede mover una salida a una hora que ya pasó.", "fecha_pasada");
            }

            $nuevas = [];
            foreach ($objetivos as $s) {
                $nuevas[$s->getId()] = $s === $salida ? \DateTime::createFromInterface($fecha) : FirmaSalida::conHora($s->getFecha(), $fecha);
            }
            // Las que se van a mover no cuentan en la agenda del bus; las que tienen asientos se quedan donde están.
            $seMueven = array_diff(array_keys($nuevas), array_keys($this->vendidos->porSalida(array_keys($nuevas))));
            $agenda = $bus === null ? new AgendaBus() : $this->agendas->de(
                [(int) $bus->getId()],
                \DateTimeImmutable::createFromInterface(min($nuevas)),
                \DateTimeImmutable::createFromInterface(max($nuevas))->modify("+3 days"),
                array_values($seMueven),
            );

            return $this->aplicar($objetivos, function (Salida $s) use ($trayecto, $bus, $nuevas, $agenda): ?array {
                $nueva = $nuevas[$s->getId()];
                if ($bus !== null && ($choque = $agenda->choque((int) $bus->getId(), $nueva, $trayecto->getDuracionEstimadaMinutos())) !== null) {
                    return ["motivo" => self::CONFLICTO, "choque" => array_diff_key($choque, ["trayectoId" => true])];
                }
                $s->setTrayecto($trayecto)->setBus($bus)->setEmpresa($bus?->getEmpresa() ?? $s->getEmpresa())->setFecha($nueva);
                $bus !== null && $agenda->ocupar((int) $bus->getId(), $nueva, $trayecto->getDuracionEstimadaMinutos(), VistaSalida::referencia($s));

                return null;
            });
        });
    }

    /** Anular = cancelar: la salida queda en la base de datos, en estado `cancelada`. */
    public function anular(int $id, bool $propagar): array
    {
        return $this->transaccion->ejecutar(function () use ($id, $propagar): array {
            [$salida, $objetivos] = $this->bloquear($id, $propagar);
            $this->exigirProgramada($salida, "anular");

            return $this->aplicar($objetivos, static function (Salida $s): ?array {
                $s->setEstado(EstadoSalida::CANCELADA);

                return null;
            });
        });
    }

    /** Borra de la base de datos (solo salidas programadas o canceladas, sin historial de boletos). */
    public function eliminar(int $id, bool $propagar): array
    {
        return $this->transaccion->ejecutar(function () use ($id, $propagar): array {
            [$salida, $objetivos] = $this->bloquear($id, $propagar);
            if (!in_array($salida->getEstado(), [EstadoSalida::PROGRAMADA, EstadoSalida::CANCELADA], true)) {
                throw new SalidaRechazada(
                    "Una salida {$salida->getEstado()->value} no se puede eliminar.",
                    "estado_invalido",
                    409,
                );
            }
            $historial = $this->vendidos->conHistorial(array_map(static fn(Salida $s) => (int) $s->getId(), $objetivos));

            return $this->aplicar($objetivos, function (Salida $s) use ($historial): ?array {
                if (isset($historial[$s->getId()])) {
                    return ["motivo" => self::HISTORIAL];
                }
                $this->em->remove($s);

                return null;
            });
        });
    }

    /**
     * Las salidas futuras idénticas a `$salida` que siguen programadas, por fecha.
     *
     * @return list<Salida>
     */
    public function identicas(Salida $salida): array
    {
        $desde = max($salida->getFecha(), new \DateTime());
        $qb = $this->em->createQueryBuilder()
            ->select("s")
            ->from(Salida::class, "s")
            ->where("s.trayecto = :trayecto")
            ->andWhere("s.estado = :programada")
            ->andWhere("s.fecha > :desde")
            ->andWhere("s.id != :id")
            ->orderBy("s.fecha", "ASC")
            ->setParameter("trayecto", $salida->getTrayecto())
            ->setParameter("programada", EstadoSalida::PROGRAMADA->value)
            ->setParameter("desde", $desde)
            ->setParameter("id", $salida->getId());
        foreach (["bus" => $salida->getBus(), "empresa" => $salida->getEmpresa()] as $campo => $valor) {
            $valor === null ? $qb->andWhere("s.{$campo} IS NULL") : $qb->andWhere("s.{$campo} = :{$campo}")->setParameter($campo, $valor);
        }
        $firma = FirmaSalida::deSalida($salida);

        /** @var list<Salida> $candidatas */
        $candidatas = $qb->getQuery()->getResult();

        return array_values(array_filter($candidatas, static fn(Salida $s) => FirmaSalida::deSalida($s) === $firma));
    }

    /**
     * Bloquea la salida y, si se propaga, sus idénticas futuras.
     *
     * @return array{0: Salida, 1: list<Salida>}
     */
    private function bloquear(int $id, bool $propagar): array
    {
        $salida = $this->em->find(Salida::class, $id, LockMode::PESSIMISTIC_WRITE)
            ?? throw new SalidaRechazada("La salida no existe.", "salida_inexistente", 404);
        $objetivos = [$salida];
        if ($propagar) {
            foreach ($this->identicas($salida) as $s) {
                $this->em->lock($s, LockMode::PESSIMISTIC_WRITE);
                $objetivos[] = $s;
            }
        }

        return [$salida, $objetivos];
    }

    /**
     * Aplica `$cambio` a cada salida sin asientos comprometidos. `$cambio`
     * devuelve null si aplicó o `{ motivo, ... }` si la omitió.
     *
     * @param list<Salida>                              $objetivos
     * @param callable(Salida): ?array<string, mixed>   $cambio
     *
     * @return array{aplicadas: list<array<string, mixed>>, omitidas: list<array<string, mixed>>}
     */
    private function aplicar(array $objetivos, callable $cambio): array
    {
        $vendidos = $this->vendidos->porSalida(array_map(static fn(Salida $s) => (int) $s->getId(), $objetivos));
        $aplicadas = $omitidas = [];
        foreach ($objetivos as $s) {
            $antes = VistaSalida::referencia($s);
            if (isset($vendidos[$s->getId()])) {
                $omitidas[] = $antes + ["motivo" => self::ASIENTOS, "asientos" => $vendidos[$s->getId()]];
                continue;
            }
            $omision = $cambio($s);
            $omision === null ? $aplicadas[] = $antes : $omitidas[] = $antes + $omision;
        }

        return ["aplicadas" => $aplicadas, "omitidas" => $omitidas];
    }

    private function exigirProgramada(Salida $salida, string $accion): void
    {
        if ($salida->getEstado() !== EstadoSalida::PROGRAMADA) {
            throw new SalidaRechazada(
                "Solo se puede {$accion} una salida programada; esta está {$salida->getEstado()->value}.",
                "estado_invalido",
                409,
            );
        }
    }

    private function salida(int $id): Salida
    {
        return $this->em->find(Salida::class, $id)
            ?? throw new SalidaRechazada("La salida no existe.", "salida_inexistente", 404);
    }

    private static function fecha(mixed $valor): \DateTime
    {
        $f = is_string($valor) ? \DateTime::createFromFormat("!Y-m-d\\TH:i", substr($valor, 0, 16)) : false;
        if ($f === false) {
            throw new SalidaRechazada("La fecha y hora no son válidas (AAAA-MM-DDTHH:MM).", "fecha_invalida");
        }

        return $f;
    }
}
