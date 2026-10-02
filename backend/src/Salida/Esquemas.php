<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Bus;
use App\Entity\EsquemaSalida;
use App\Entity\EsquemaSalidaMomento;
use App\Entity\Trayecto;
use App\Entity\Usuario;
use App\Salida\Programacion\Momento;
use App\Salida\Programacion\Programacion;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Esquemas de salidas guardados (ADR-024): se crean desde el programador
 * ("guardar esta configuración") y ahí mismo se cargan, cambian (buses,
 * horas, quitar un momento) o borran. Aislados por empresa (TenantFilter).
 */
final class Esquemas
{
    public const MAX_NOMBRE = 100;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        /** @var list<EsquemaSalida> $esquemas */
        $esquemas = $this->em->createQueryBuilder()
            ->select("e", "m", "t", "o", "d", "b")
            ->from(EsquemaSalida::class, "e")
            ->join("e.trayecto", "t")
            ->join("t.origen", "o")
            ->join("t.destino", "d")
            ->leftJoin("e.momentos", "m")
            ->leftJoin("m.bus", "b")
            ->orderBy("e.nombre", "ASC")
            ->addOrderBy("m.hora", "ASC")
            ->getQuery()
            ->getResult();

        return array_map(self::vista(...), $esquemas);
    }

    /**
     * Crea (`$id` nulo) o reemplaza un esquema.
     * `$datos`: `{ nombre, trayectoId, intervaloDias, momentos: [{ hora, busId }] }`.
     *
     * @param array<string, mixed> $datos
     *
     * @return array<string, mixed>
     */
    public function guardar(?int $id, array $datos, ?Usuario $usuario): array
    {
        $nombre = trim((string) ($datos["nombre"] ?? ""));
        if ($nombre === "" || mb_strlen($nombre) > self::MAX_NOMBRE) {
            throw new SalidaRechazada("El esquema necesita un nombre de 1 a " . self::MAX_NOMBRE . " caracteres.", "nombre_invalido");
        }
        $trayecto = $this->em->find(Trayecto::class, (int) ($datos["trayectoId"] ?? 0))
            ?? throw new SalidaRechazada("Elija el trayecto.", "trayecto_requerido");
        $intervalo = (int) ($datos["intervaloDias"] ?? 1);
        if ($intervalo < 1 || $intervalo > Programacion::MAX_INTERVALO) {
            throw new SalidaRechazada("El intervalo debe estar entre 1 y " . Programacion::MAX_INTERVALO . " días.", "intervalo_invalido");
        }
        $momentos = Momento::lista($datos["momentos"] ?? null, Programacion::MAX_MOMENTOS);
        $buses = $this->buses(array_map(static fn(Momento $m) => $m->busId, $momentos));

        if ($id === null) {
            $esquema = new EsquemaSalida($nombre, $usuario?->getEmpresa(), $trayecto);
            $this->em->persist($esquema);
        } else {
            $esquema = $this->buscar($id);
        }
        $this->exigirNombreLibre($nombre, $esquema);

        $esquema->reemplazar(
            $nombre,
            $trayecto,
            $intervalo,
            array_map(static fn(Momento $m) => ["hora" => $m->hora, "bus" => $buses[$m->busId]], $momentos),
            $usuario,
        );
        $this->em->flush();

        return self::vista($esquema);
    }

    public function eliminar(int $id): void
    {
        $this->em->remove($this->buscar($id));
        $this->em->flush();
    }

    /**
     * Los buses pedidos, por id; rechaza los que no existen o no son de la
     * empresa del usuario (el TenantFilter los oculta).
     *
     * @param list<int> $ids
     *
     * @return array<int, Bus>
     */
    public function buses(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        $buses = [];
        foreach ($this->em->getRepository(Bus::class)->findBy(["id" => $ids]) as $b) {
            $buses[(int) $b->getId()] = $b;
        }
        $faltan = array_diff($ids, array_keys($buses));
        if ($faltan !== []) {
            throw new SalidaRechazada("El bus #" . implode(", #", $faltan) . " no existe o no es de su empresa.", "bus_inexistente");
        }

        return $buses;
    }

    private function buscar(int $id): EsquemaSalida
    {
        return $this->em->find(EsquemaSalida::class, $id)
            ?? throw new SalidaRechazada("El esquema ya no existe.", "esquema_inexistente", 404);
    }

    private function exigirNombreLibre(string $nombre, EsquemaSalida $esquema): void
    {
        $qb = $this->em->createQueryBuilder()
            ->select("COUNT(e.id)")
            ->from(EsquemaSalida::class, "e")
            ->where("LOWER(e.nombre) = LOWER(:nombre)")
            ->setParameter("nombre", $nombre);
        if ($esquema->getEmpresa() === null) {
            $qb->andWhere("e.empresa IS NULL");
        } else {
            $qb->andWhere("e.empresa = :empresa")->setParameter("empresa", $esquema->getEmpresa());
        }
        if ($esquema->getId() !== null) {
            $qb->andWhere("e.id != :id")->setParameter("id", $esquema->getId());
        }
        if ((int) $qb->getQuery()->getSingleScalarResult() > 0) {
            throw new SalidaRechazada("Ya hay un esquema llamado «{$nombre}».", "nombre_repetido", 409);
        }
    }

    /** @return array<string, mixed> */
    public static function vista(EsquemaSalida $e): array
    {
        return [
            "id" => $e->getId(),
            "nombre" => $e->getNombre(),
            "trayecto" => VistaSalida::trayecto($e->getTrayecto()),
            "intervaloDias" => $e->getIntervaloDias(),
            "momentos" => array_map(static fn(EsquemaSalidaMomento $m) => [
                "hora" => $m->getHora(),
                "busId" => $m->getBus()->getId(),
                "bus" => $m->getBus()->getCodigo(),
            ], array_values($e->getMomentos()->toArray())),
            "actualizadoEn" => $e->getActualizadoEn()->format(DATE_ATOM),
            "actualizadoPor" => $e->getActualizadoPor()?->getUsername(),
        ];
    }
}
