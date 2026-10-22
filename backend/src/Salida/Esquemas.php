<?php

declare(strict_types=1);

namespace App\Salida;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\FiltroAlcance;
use App\Acceso\Ubicacion;
use App\Entity\Bus;
use App\Entity\Empresa;
use App\Entity\EsquemaSalida;
use App\Entity\EsquemaSalidaMomento;
use App\Entity\Ruta;
use App\Entity\Usuario;
use App\Salida\Programacion\Momento;
use App\Salida\Programacion\Programacion;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Esquemas de salidas guardados (ADR-024): se crean desde el programador
 * ("guardar esta configuración") y ahí mismo se cargan, cambian (buses,
 * horas, quitar un momento) o borran. Aislados por empresa: cada uno es de
 * la empresa de sus buses, y solo se ven y tocan los de las empresas donde se
 * programa (`salida.crear`, ADR-039).
 */
final class Esquemas
{
    public const MAX_NOMBRE = 100;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("e", "m", "r", "b")
            ->from(EsquemaSalida::class, "e")
            ->join("e.ruta", "r")
            ->leftJoin("e.momentos", "m")
            ->leftJoin("m.bus", "b")
            ->orderBy("e.nombre", "ASC")
            ->addOrderBy("m.hora", "ASC");
        /** @var list<EsquemaSalida> $esquemas */
        $esquemas = FiltroAlcance::aplicar($qb, $this->autorizacion->alcance(Capacidad::SALIDA_CREAR), "e.empresa", compartidos: true)
            ->getQuery()
            ->getResult();

        return array_map(self::vista(...), $esquemas);
    }

    /**
     * Crea (`$id` nulo) o reemplaza un esquema.
     * `$datos`: `{ nombre, rutaId, intervaloDias, momentos: [{ hora, busId }] }`.
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
        $ruta = $this->em->find(Ruta::class, (int) ($datos["rutaId"] ?? 0))
            ?? throw new SalidaRechazada("Elija la ruta.", "ruta_requerida");
        $intervalo = (int) ($datos["intervaloDias"] ?? 1);
        if ($intervalo < 1 || $intervalo > Programacion::MAX_INTERVALO) {
            throw new SalidaRechazada("El intervalo debe estar entre 1 y " . Programacion::MAX_INTERVALO . " días.", "intervalo_invalido");
        }
        $momentos = Momento::lista($datos["momentos"] ?? null, Programacion::MAX_MOMENTOS);
        $buses = $this->buses(array_map(static fn(Momento $m) => $m->busId, $momentos));

        if ($id === null) {
            $empresa = $this->empresaDe($buses);
            $esquema = new EsquemaSalida($nombre, $empresa === null ? null : $this->em->getReference(Empresa::class, $empresa), $ruta);
            $this->em->persist($esquema);
        } else {
            $esquema = $this->buscar($id);
        }
        $this->exigirNombreLibre($nombre, $esquema);

        $esquema->reemplazar(
            $nombre,
            $ruta,
            $intervalo,
            array_map(static fn(Momento $m) => ["hora" => $m->hora, "bus" => $buses[$m->busId]], $momentos),
            $usuario,
        );
        $this->em->flush();

        return self::vista($esquema);
    }

    public function eliminar(int $id): void
    {
        // `buscar` ya exige que sea de su alcance.
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
    public function buses(array $ids, Capacidad $capacidad = Capacidad::SALIDA_CREAR): array
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
        foreach ($buses as $id => $b) {
            if (!$this->autorizacion->puede($capacidad, Ubicacion::empresa($b->getEmpresa()?->getId()))) {
                throw new SalidaRechazada(sprintf("Fuera de su alcance: el bus #%d es de una empresa donde usted no programa salidas.", $id), "alcance", 403);
            }
        }

        return $buses;
    }

    /**
     * La empresa del esquema: la de sus buses si es una sola; si no, la única
     * de su alcance; si tampoco, ninguna (compartido).
     *
     * @param array<int, Bus> $buses
     */
    private function empresaDe(array $buses): ?int
    {
        $empresas = array_values(array_unique(array_map(static fn(Bus $b) => $b->getEmpresa()?->getId(), $buses)));

        return count($empresas) === 1 && $empresas[0] !== null
            ? $empresas[0]
            : $this->autorizacion->alcance(Capacidad::SALIDA_CREAR)->empresaUnica();
    }

    private function buscar(int $id): EsquemaSalida
    {
        $esquema = $this->em->find(EsquemaSalida::class, $id)
            ?? throw new SalidaRechazada("El esquema ya no existe.", "esquema_inexistente", 404);
        if (!$this->autorizacion->puede(Capacidad::SALIDA_CREAR, Ubicacion::empresa($esquema->getEmpresa()?->getId()))) {
            throw new SalidaRechazada("Fuera de su alcance: el esquema es de otra empresa.", "alcance", 403);
        }

        return $esquema;
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
            "ruta" => VistaSalida::rutaOpcion($e->getRuta()),
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
