<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Entity\Enclave;
use App\Entity\Parada;
use App\Entity\Ruta;
use App\Entity\Salida;
use App\Entity\SalidaParada;
use Doctrine\ORM\EntityManagerInterface;

/** Por dónde pasan las rutas y las salidas, para elegir la ruta y embarcar (ADR-035). */
final class RutasEncomienda
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Enclaves de la ruta, en orden.
     *
     * @return list<int>
     */
    public static function enclaves(Ruta $ruta): array
    {
        return array_values(array_map(static fn(Parada $p) => (int) $p->getEnclave()->getId(), $ruta->getParadas()->toArray()));
    }

    /**
     * Paradas de la salida (su horario, ADR-030; si no lo tiene, las de su ruta), en orden.
     *
     * @return list<array{id: int, nombre: string}>
     */
    public static function paradasDeSalida(Salida $salida): array
    {
        $horario = $salida->getHorario()->toArray();
        $enclaves = $horario !== []
            ? array_map(static fn(SalidaParada $p) => $p->getEnclave(), $horario)
            : array_map(static fn(Parada $p) => $p->getEnclave(), $salida->getRuta()?->getParadas()->toArray() ?? []);

        return array_values(array_map(static fn(Enclave $e) => ["id" => (int) $e->getId(), "nombre" => (string) $e->getNombre()], $enclaves));
    }

    /** @return list<int> */
    public static function idsDeSalida(Salida $salida): array
    {
        return array_column(self::paradasDeSalida($salida), "id");
    }

    /**
     * Rutas activas que van del origen al destino (en ese orden).
     *
     * @return list<Ruta>
     */
    public function entre(int $origen, int $destino): array
    {
        /** @var list<Ruta> $rutas */
        $rutas = $this->em->createQueryBuilder()
            ->select("r")
            ->from(Ruta::class, "r")
            ->join(Parada::class, "a", "WITH", "a.ruta = r AND a.enclave = :origen")
            ->join(Parada::class, "b", "WITH", "b.ruta = r AND b.enclave = :destino AND b.orden > a.orden")
            ->where("r.activo = true")
            ->setParameter("origen", $origen)
            ->setParameter("destino", $destino)
            ->orderBy("r.nombre", "ASC")
            ->getQuery()
            ->getResult();

        return $rutas;
    }
}
