<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Enum\PrecisionHora;
use App\Entity\Parada;
use App\Entity\Salida;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Lee el `Itinerario` de una salida desde su horario (`salida_parada`,
 * ADR-030) y los trayectos que unen sus paradas. Memoiza por petición (se
 * vacía en cada request: FrankenPHP en modo worker reutiliza el servicio).
 * Una salida sin horario (no debería haberla) toma las paradas de su ruta.
 */
final class Itinerarios implements ResetInterface
{
    /** @var array<int, Itinerario> por id de salida */
    private array $cache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function deSalida(Salida $salida): Itinerario
    {
        return $this->deSalidas([$salida])[$salida->getId()];
    }

    /**
     * @param iterable<Salida> $salidas
     *
     * @return array<int, Itinerario> por id de salida
     */
    public function deSalidas(iterable $salidas): array
    {
        $faltan = [];
        $resultado = [];
        foreach ($salidas as $s) {
            if (isset($this->cache[$s->getId()])) {
                $resultado[$s->getId()] = $this->cache[$s->getId()];
            } else {
                $faltan[$s->getId()] = $s;
            }
        }
        if ($faltan === []) {
            return $resultado;
        }

        $db = $this->em->getConnection();
        $ids = array_keys($faltan);
        $paradas = [];
        $horas = [];
        foreach ($db->fetchAllAssociative(
            "SELECT salida_id, enclave_id, hora, precision FROM salida_parada WHERE salida_id IN (:ids) ORDER BY salida_id, orden",
            ["ids" => $ids],
            ["ids" => ArrayParameterType::INTEGER],
        ) as $f) {
            $s = (int) $f["salida_id"];
            $e = (int) $f["enclave_id"];
            $paradas[$s][] = $e;
            $horas[$s][$e] = [
                "hora" => new \DateTimeImmutable((string) $f["hora"]),
                "precision" => PrecisionHora::from((string) $f["precision"]),
            ];
        }
        $trayectos = [];
        foreach ($db->fetchAllAssociative(
            "SELECT o.salida_id, t.id, t.origen_id, t.destino_id
               FROM salida_parada o
               JOIN salida_parada d ON d.salida_id = o.salida_id AND d.orden > o.orden
               JOIN trayecto t ON t.origen_id = o.enclave_id AND t.destino_id = d.enclave_id
              WHERE o.salida_id IN (:ids)",
            ["ids" => $ids],
            ["ids" => ArrayParameterType::INTEGER],
        ) as $f) {
            $trayectos[(int) $f["salida_id"]][(int) $f["id"]] = ["origen" => (int) $f["origen_id"], "destino" => (int) $f["destino_id"]];
        }

        foreach ($faltan as $id => $s) {
            $resultado[$id] = $this->cache[$id] = Itinerario::deParadas(
                $paradas[$id] ?? array_map(static fn(Parada $p) => (int) $p->getEnclave()->getId(), $s->getRuta()->getParadas()->toArray()),
                $trayectos[$id] ?? [],
                $horas[$id] ?? [],
            );
        }

        return $resultado;
    }

    /** Olvida lo leído de una salida (su horario cambió en esta petición). */
    public function olvidar(int $salidaId): void
    {
        unset($this->cache[$salidaId]);
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
