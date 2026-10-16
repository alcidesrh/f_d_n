<?php

declare(strict_types=1);

namespace App\Tarifa;

use App\Entity\BoletoTarifa;
use App\Entity\Enclave;
use App\Entity\Parada;
use App\Entity\Ruta;
use App\Entity\Trayecto;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tarifas para el mapa de tarifas (`/tarifas/mapa`): por ruta (sus paradas con
 * ubicación y los trayectos de sus pares de paradas, ADR-030) o por un par
 * origen→destino suelto. Solo lectura: la gestión va por los formularios de
 * `BoletoTarifa`.
 */
final class MapaTarifas
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * `null` si la ruta no existe. Los trayectos vienen en el orden de sus
     * paradas (`desde`/`hasta` son índices en `paradas`) y solo los que tienen
     * tarifas.
     *
     * @return array{ruta: array{id: int, nombre: string, paradas: list<array<string, mixed>>}, trayectos: list<array<string, mixed>>}|null
     */
    public function ruta(int $rutaId): ?array
    {
        $ruta = $this->em->find(Ruta::class, $rutaId);
        if (!$ruta instanceof Ruta) {
            return null;
        }

        $paradas = $ruta->getParadas()->toArray();
        usort($paradas, static fn(Parada $a, Parada $b) => $a->getOrden() <=> $b->getOrden());
        $enclaves = array_map(static fn(Parada $p) => $p->getEnclave(), $paradas);

        return [
            "ruta" => ["id" => (int) $ruta->getId(), "nombre" => $ruta->getNombre(), "paradas" => $this->paradas($enclaves)],
            "trayectos" => $this->trayectos($enclaves),
        ];
    }

    /**
     * Las tarifas del trayecto origen→destino, con la línea entre sus dos
     * enclaves. `null` si alguno no existe o son el mismo. Sin tarifas, la
     * línea sale igual y `trayectos` va vacío.
     *
     * @return array{ruta: array{id: null, nombre: string, paradas: list<array<string, mixed>>}, trayectos: list<array<string, mixed>>}|null
     */
    public function trayecto(int $origenId, int $destinoId): ?array
    {
        if ($origenId === $destinoId) {
            return null;
        }
        $origen = $this->em->find(Enclave::class, $origenId);
        $destino = $this->em->find(Enclave::class, $destinoId);
        if (!$origen instanceof Enclave || !$destino instanceof Enclave) {
            return null;
        }
        $enclaves = [$origen, $destino];

        return [
            "ruta" => ["id" => null, "nombre" => $origen->getNombre() . " → " . $destino->getNombre(), "paradas" => $this->paradas($enclaves)],
            "trayectos" => $this->trayectos($enclaves),
        ];
    }

    /**
     * @param list<Enclave> $enclaves en orden
     * @return list<array<string, mixed>>
     */
    private function paradas(array $enclaves): array
    {
        return array_map(static fn(Enclave $e, int $i) => [
            "orden" => $i,
            "enclaveId" => (int) $e->getId(),
            "nombre" => $e->getNombre(),
            "latitud" => self::coordenada($e->getLatitud()),
            "longitud" => self::coordenada($e->getLongitud()),
        ], $enclaves, array_keys($enclaves));
    }

    /**
     * @param list<Enclave> $enclaves en orden de la ruta
     * @return list<array<string, mixed>>
     */
    private function trayectos(array $enclaves): array
    {
        $ids = array_values(array_unique(array_map(static fn(Enclave $e) => (int) $e->getId(), $enclaves)));
        if (count($ids) < 2) {
            return [];
        }

        // Un trayecto por par (origen, destino); la ruta puede repetir un enclave.
        $porPar = [];
        foreach ($this->em->createQueryBuilder()
            ->select("t")->from(Trayecto::class, "t")
            ->where("IDENTITY(t.origen) IN (:ids)")->andWhere("IDENTITY(t.destino) IN (:ids)")
            ->setParameter("ids", $ids)
            ->getQuery()->getResult() as $t) {
            $porPar[$t->getOrigen()->getId() . "-" . $t->getDestino()->getId()] = $t;
        }

        // Pares en orden de la ruta (i < j), con su trayecto si existe.
        $pares = [];
        for ($i = 0; $i < count($enclaves); $i++) {
            for ($j = $i + 1; $j < count($enclaves); $j++) {
                $clave = $enclaves[$i]->getId() . "-" . $enclaves[$j]->getId();
                if (isset($porPar[$clave])) {
                    $pares[] = [$i, $j, $porPar[$clave]];
                }
            }
        }
        if ($pares === []) {
            return [];
        }

        $tarifas = $this->em->createQueryBuilder()
            ->select("b")->from(BoletoTarifa::class, "b")
            ->where("b.trayecto IN (:trayectos)")
            ->setParameter("trayectos", array_map(static fn(array $p) => $p[2], $pares))
            ->orderBy("b.vigenteDesde", "DESC")
            ->getQuery()->getResult();

        $agrupadas = [];
        foreach ($tarifas as $tarifa) {
            $agrupadas[(int) $tarifa->getTrayecto()->getId()][] = $this->tarifa($tarifa);
        }

        $salida = [];
        foreach ($pares as [$i, $j, $trayecto]) {
            if (!isset($agrupadas[(int) $trayecto->getId()])) {
                continue;
            }
            $salida[] = [
                "id" => (int) $trayecto->getId(),
                "desde" => $i,
                "hasta" => $j,
                "origen" => $enclaves[$i]->getNombre(),
                "destino" => $enclaves[$j]->getNombre(),
                "tarifas" => $agrupadas[(int) $trayecto->getId()],
            ];
        }

        return $salida;
    }

    /** @return array<string, mixed> */
    private function tarifa(BoletoTarifa $t): array
    {
        return [
            "id" => (int) $t->getId(),
            "nombre" => $t->getNombre(),
            "clase" => $t->getClase()->value,
            "empresaId" => $t->getEmpresa()?->getId(),
            "empresa" => $t->getEmpresa()?->getNombre(),
            "busId" => $t->getBus()?->getId(),
            "bus" => $t->getBus()?->getCodigo(),
            "busClaseId" => $t->getBusClase()?->getId(),
            "busClase" => $t->getBusClase()?->getNombre(),
            "precio" => $t->getPrecioMonto(),
            "importe" => $t->getImporte(),
            "horaDesde" => $t->getHoraDesde()?->format("H:i"),
            "horaHasta" => $t->getHoraHasta()?->format("H:i"),
            "vigenteDesde" => $t->getVigenteDesde()->format("Y-m-d H:i"),
            "usuario" => $t->getUsuario()?->getFullName() ?? $t->getUsuario()?->getUsername(),
        ];
    }

    private static function coordenada(?string $valor): ?float
    {
        return $valor === null || $valor === "" ? null : (float) $valor;
    }
}
