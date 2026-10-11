<?php

declare(strict_types=1);

namespace App\Salida;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use App\Entity\Ruta;
use App\Bitacora\RegistradorBitacora;
use App\Ruta\Horarios;
use App\Venta\PublicadorOcupacion;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Editar, anular y eliminar salidas (ADR-024), cada operación con la opción
 * de propagarse a las salidas futuras idénticas (`FirmaSalida`: mismo
 * ruta, bus, empresa y hora; solo cambia el día) que siguen programadas.
 *
 * Una salida con asientos comprometidos (`AsientosVendidos`) solo admite
 * cambiar el bus por otro con la misma distribución (molde de croquis, que
 * fija número y clase de cada asiento): sus boletos y reservas pasan al
 * asiento del mismo número (ADR-031). Cualquier otro cambio se omite
 * (`omitidas`) para que el usuario reasigne o anule esos asientos a mano. Tampoco se mueve una salida a una hora en que el bus está
 * en otro viaje (`conflicto`), ni se borra una con historial de boletos
 * (`historial`: se anula). Las salidas afectadas se bloquean (como en la venta)
 * para que no se venda un asiento entre la comprobación y el cambio.
 */
final class GestionSalidas
{
    public const ASIENTOS = "asientos";
    public const CONFLICTO = "conflicto";
    public const HISTORIAL = "historial";
    /** Con asientos vendidos, el bus nuevo no tiene la misma distribución. */
    public const OTRO_CROQUIS = "otro_croquis";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AsientosVendidos $vendidos,
        private readonly AgendaFlota $agendas,
        private readonly Esquemas $esquemas,
        private readonly Transaccion $transaccion,
        private readonly EventDispatcherInterface $eventos,
        private readonly Horarios $horarios,
        private readonly PublicadorOcupacion $ocupacion,
        private readonly RegistradorBitacora $bitacora,
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
     * `$datos`: `{ rutaId?, busId?, fecha?: AAAA-MM-DDTHH:MM }`. La salida
     * toma la fecha completa; las idénticas conservan su día y toman la hora.
     * A las que tienen asientos solo se les cambia el bus, y solo por uno
     * con la misma distribución (los asientos pasan por número).
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

            $ruta = isset($datos["rutaId"])
                ? ($this->em->find(Ruta::class, (int) $datos["rutaId"]) ?? throw new SalidaRechazada("La ruta no existe.", "ruta_inexistente", 404))
                : $salida->getRuta();
            if ($ruta !== $salida->getRuta() && !$ruta->isActivo()) {
                throw new SalidaRechazada("La ruta " . VistaSalida::ruta($ruta) . " está inactiva.", "ruta_inactiva");
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

            $duracion = $ruta->duracionPara($bus?->getClase());
            $cambiarBus = function (Salida $s) use ($bus, $agenda, $duracion): ?array {
                if ($bus !== null && ($choque = $agenda->choque((int) $bus->getId(), $s->getFecha(), $duracion)) !== null) {
                    return ["motivo" => self::CONFLICTO, "choque" => array_diff_key($choque, ["rutaId" => true])];
                }
                $s->setBus($bus)->setEmpresa($bus?->getEmpresa() ?? $s->getEmpresa());
                $bus !== null && $agenda->ocupar((int) $bus->getId(), $s->getFecha(), $duracion, VistaSalida::referencia($s));

                return null;
            };
            $conAsientos = [];
            $resultado = $this->aplicar(
                $objetivos,
                function (Salida $s) use ($ruta, $bus, $nuevas, $agenda, $duracion): ?array {
                    $nueva = $nuevas[$s->getId()];
                    if ($bus !== null && ($choque = $agenda->choque((int) $bus->getId(), $nueva, $duracion)) !== null) {
                        return ["motivo" => self::CONFLICTO, "choque" => array_diff_key($choque, ["rutaId" => true])];
                    }
                    $s->setRuta($ruta)->setBus($bus)->setEmpresa($bus?->getEmpresa() ?? $s->getEmpresa())->setFecha($nueva);
                    $bus !== null && $agenda->ocupar((int) $bus->getId(), $nueva, $duracion, VistaSalida::referencia($s));

                    return null;
                },
                // Con asientos: solo el bus, por uno de la misma distribución, y sin mover la salida.
                function (Salida $s, int $asientos) use ($ruta, $bus, $nuevas, $cambiarBus, &$conAsientos): ?array {
                    $antes = $s->getBus();
                    if ($ruta !== $s->getRuta() || $nuevas[$s->getId()] != $s->getFecha() || $bus === $antes) {
                        return ["motivo" => self::ASIENTOS, "asientos" => $asientos];
                    }
                    if ($bus === null || $antes === null || $bus->getCroquis() === null || $bus->getCroquis() !== $antes->getCroquis()) {
                        return ["motivo" => self::OTRO_CROQUIS, "asientos" => $asientos];
                    }
                    $omision = $cambiarBus($s);
                    if ($omision === null) {
                        $conAsientos[] = (int) $s->getId();
                        $this->bitacora->anotar($s, ["asientosPorNumero" => $asientos]);
                    }

                    return $omision;
                },
            );
            // Los boletos y reservas pasan al asiento del mismo número del bus nuevo.
            $this->em->flush();
            foreach ($conAsientos as $salidaId) {
                $this->pasarAsientos($salidaId, (int) $bus->getId());
            }
            // El horario se rehace con la fecha, la ruta y el bus nuevos (las que tienen asientos conservan sus paradas).
            $this->horarios->fijar(...array_map(static fn(array $a) => (int) $a["id"], $resultado["aplicadas"]));
            foreach ($conAsientos as $salidaId) {
                $this->transaccion->despuesDeConfirmar(fn() => $this->ocupacion->cambio($salidaId));
            }

            return $resultado;
        });
    }

    /** Anular = cancelar: la salida queda en la base de datos, en estado `cancelada`. */
    public function anular(int $id, bool $propagar): array
    {
        return $this->transaccion->ejecutar(function () use ($id, $propagar): array {
            [$salida, $objetivos] = $this->bloquear($id, $propagar);
            $this->exigirProgramada($salida, "anular");

            $resultado = $this->aplicar($objetivos, static function (Salida $s): ?array {
                $s->setEstado(EstadoSalida::CANCELADA);

                return null;
            });
            $ids = array_values(array_filter(array_map(static fn(array $r) => $r["id"] ?? null, $resultado["aplicadas"])));
            if ($ids !== []) {
                $this->transaccion->despuesDeConfirmar(fn() => $this->eventos->dispatch(new SalidasAnuladas($ids)));
            }

            return $resultado;
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
            ->where("s.ruta = :ruta")
            ->andWhere("s.estado = :programada")
            ->andWhere("s.fecha > :desde")
            ->andWhere("s.id != :id")
            ->orderBy("s.fecha", "ASC")
            ->setParameter("ruta", $salida->getRuta())
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
     * Aplica `$cambio` a cada salida sin asientos comprometidos y
     * `$conAsientos` (si se da) a las que tienen; sin él, esas se omiten.
     * Ambos devuelven null si aplicaron o `{ motivo, ... }` si la omitieron.
     *
     * @param list<Salida>                                   $objetivos
     * @param callable(Salida): ?array<string, mixed>        $cambio
     * @param ?callable(Salida, int): ?array<string, mixed>  $conAsientos
     *
     * @return array{aplicadas: list<array<string, mixed>>, omitidas: list<array<string, mixed>>}
     */
    private function aplicar(array $objetivos, callable $cambio, ?callable $conAsientos = null): array
    {
        $vendidos = $this->vendidos->porSalida(array_map(static fn(Salida $s) => (int) $s->getId(), $objetivos));
        $aplicadas = $omitidas = [];
        foreach ($objetivos as $s) {
            $antes = VistaSalida::referencia($s);
            if (isset($vendidos[$s->getId()])) {
                $omision = $conAsientos !== null ? $conAsientos($s, $vendidos[$s->getId()]) : ["motivo" => self::ASIENTOS, "asientos" => $vendidos[$s->getId()]];
                $omision === null ? $aplicadas[] = $antes : $omitidas[] = $antes + $omision;
                continue;
            }
            $omision = $cambio($s);
            $omision === null ? $aplicadas[] = $antes : $omitidas[] = $antes + $omision;
        }

        return ["aplicadas" => $aplicadas, "omitidas" => $omitidas];
    }

    /**
     * Pasa los boletos (de cualquier estado) y las reservas de la salida al
     * asiento del mismo número del bus nuevo, que tiene la misma distribución.
     */
    private function pasarAsientos(int $salidaId, int $busId): void
    {
        $db = $this->em->getConnection();
        foreach (["boleto_asiento", "reserva_asiento"] as $tabla) {
            $db->executeStatement(
                "UPDATE {$tabla} t SET asiento_id = nuevo.id
                   FROM asiento viejo, asiento nuevo
                  WHERE t.salida_id = :salida AND viejo.id = t.asiento_id
                    AND nuevo.bus_id = :bus AND nuevo.numero = viejo.numero AND viejo.bus_id <> :bus",
                ["salida" => $salidaId, "bus" => $busId],
            );
            $sueltos = (int) $db->fetchOne(
                "SELECT COUNT(*) FROM {$tabla} t JOIN asiento a ON a.id = t.asiento_id WHERE t.salida_id = :salida AND a.bus_id <> :bus",
                ["salida" => $salidaId, "bus" => $busId],
            );
            if ($sueltos > 0) {
                // No debería pasar con la misma distribución; deshace todo el cambio.
                throw new SalidaRechazada("El bus nuevo no tiene todos los números de asiento vendidos.", "asientos_sin_par", 409);
            }
        }
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
