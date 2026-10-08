<?php

declare(strict_types=1);

namespace App\Migration\Salida;

use App\Migration\Mapeador;
use Doctrine\DBAL\Connection;

/**
 * Trayecto de la ruta (`ruta`) de una salida del legado, con la misma forma que
 * la migración de estáticos: el trayecto origen→destino de la ruta y, como
 * subtrayectos suyos, todos los pares hacia adelante de sus paradas
 * (A→B, A→C, …, B→C, …), de modo que `App\Venta\Itinerario` ordena las
 * paradas y cada boleto (que puede ser un tramo) cae en un trayecto vendible.
 */
class TrayectosLegado
{
    /** @var array<string, RutaMigrada|null> por código de ruta */
    private array $rutas = [];

    public function __construct(
        private readonly Connection $db,
        private readonly LectorLegado $legado,
        private readonly DependenciasLegado $dependencias,
        private readonly Mapeador $mapeador,
    ) {}

    /** Null si la ruta no existe o sus estaciones extremas no se pueden migrar. */
    public function deRuta(?string $codigo): ?RutaMigrada
    {
        $codigo = trim((string) $codigo);
        if ($codigo === "") {
            return null;
        }
        if (array_key_exists($codigo, $this->rutas)) {
            return $this->rutas[$codigo];
        }

        $ruta = $this->legado->fila("SELECT * FROM ruta WHERE codigo = :codigo", ["codigo" => $codigo]);
        $origen = $this->dependencias->estacion($ruta["estacion_origen_id"] ?? null);
        $destino = $this->dependencias->estacion($ruta["estacion_destino_id"] ?? null);
        if ($ruta === null || $origen === null || $destino === null || $origen === $destino) {
            return $this->rutas[$codigo] = null;
        }

        $id = $this->buscar($origen, $destino)
            ?? $this->crear($this->mapeador->trayecto($ruta, $origen, $destino, true));

        $paradas = [$origen];
        foreach ($this->legado->filas(
            "SELECT estacion_id FROM ruta_estacion_item WHERE ruta_codigo = :codigo ORDER BY posicion ASC",
            ["codigo" => $codigo],
        ) as $item) {
            $parada = $this->dependencias->estacion($item["estacion_id"]);
            if ($parada !== null && !in_array($parada, $paradas, true) && $parada !== $destino) {
                $paradas[] = $parada;
            }
        }
        $paradas[] = $destino;

        $pares = ["{$origen}:{$destino}" => $id];
        $n = count($paradas);
        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($i === 0 && $j === $n - 1) {
                    continue;
                }
                [$o, $d] = [$paradas[$i], $paradas[$j]];
                $sub = $this->buscar($o, $d) ?? $this->crear([
                    "origen_id" => $o,
                    "destino_id" => $d,
                    "distancia_km" => null,
                    "duracion_estimada_minutos" => null,
                    "activo" => "1",
                    "legacy_id" => sprintf("%s-SUB-%d-%d", $codigo, $o, $d),
                ]);
                $this->enlazar($id, $sub);
                $pares["{$o}:{$d}"] = $sub;
            }
        }

        return $this->rutas[$codigo] = new RutaMigrada($id, $origen, $destino, $pares);
    }

    /**
     * Trayecto de un boleto que va entre dos estaciones que no son un par de
     * la ruta (datos del legado fuera de recorrido): se usa el trayecto de
     * ese par sin enlazarlo, para no agregar paradas falsas al itinerario.
     */
    public function suelto(int $origen, int $destino): int
    {
        return $this->buscar($origen, $destino) ?? $this->crear([
            "origen_id" => $origen,
            "destino_id" => $destino,
            "distancia_km" => null,
            "duracion_estimada_minutos" => null,
            "activo" => "1",
            "legacy_id" => null,
        ]);
    }

    /** Tras revertir una salida: los trayectos creados en ella ya no existen. */
    public function olvidar(): void
    {
        $this->rutas = [];
    }

    private function buscar(int $origen, int $destino): ?int
    {
        $id = $this->db->fetchOne(
            "SELECT id FROM trayecto WHERE origen_id = :o AND destino_id = :d",
            ["o" => $origen, "d" => $destino],
        );

        return $id === false ? null : (int) $id;
    }

    private function crear(array $data): int
    {
        $id = (int) $this->db->fetchOne(
            "INSERT INTO trayecto (origen_id, destino_id, distancia_km, duracion_estimada_minutos, activo, legacy_id)
             VALUES (:origen_id, :destino_id, :distancia_km, :duracion_estimada_minutos, :activo, :legacy_id) RETURNING id",
            $data,
        );
        $this->dependencias->contar("trayecto");

        return $id;
    }

    private function enlazar(int $padre, int $hijo): void
    {
        $this->db->executeStatement(
            "INSERT INTO subtrayecto (trayecto_id, below_to_id, activo)
             SELECT :hijo, :padre, true
             WHERE NOT EXISTS (SELECT 1 FROM subtrayecto WHERE below_to_id = :padre AND trayecto_id = :hijo)",
            ["hijo" => $hijo, "padre" => $padre],
        );
    }
}
