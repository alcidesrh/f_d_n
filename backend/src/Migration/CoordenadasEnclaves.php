<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Conserva las coordenadas de los enclaves entre migraciones. El legado tiene
 * muchas estaciones sin GPS y un reset de la base las borraría junto con lo
 * geocodificado o puesto a mano. Se guardan en `resources/enclave_coordenadas.json`
 * (versionado, por id de enclave = id de estación del legado):
 *
 * - guardar(): antes de vaciar la base, vuelca al archivo las coordenadas
 *   presentes (suma y actualiza; nunca borra una entrada).
 * - completar(): tras migrar las estaciones, rellena solo los enclaves que
 *   siguen sin coordenadas; las que trae el legado mandan.
 */
final class CoordenadasEnclaves
{
    private readonly string $archivo;

    public function __construct(
        private readonly Connection $db,
        #[Autowire('%kernel.project_dir%')] string $dir,
    ) {
        $this->archivo = $dir . '/resources/enclave_coordenadas.json';
    }

    /** @return array<int, array{nombre: string, latitud: float, longitud: float}> */
    public function leer(): array
    {
        if (!is_file($this->archivo)) {
            return [];
        }
        $datos = json_decode((string) file_get_contents($this->archivo), true);

        return is_array($datos) ? $datos : [];
    }

    /** Vuelca las coordenadas de la base al archivo; devuelve cuántas entradas quedan. */
    public function guardar(): int
    {
        try {
            $filas = $this->db->fetchAllAssociative('SELECT id, nombre, latitud, longitud FROM enclave WHERE latitud IS NOT NULL AND longitud IS NOT NULL ORDER BY id');
        } catch (\Throwable) {
            return count($this->leer()); // base sin esquema: nada que guardar
        }
        $datos = $this->leer();
        foreach ($filas as $f) {
            $datos[(int) $f['id']] = ['nombre' => $f['nombre'], 'latitud' => (float) $f['latitud'], 'longitud' => (float) $f['longitud']];
        }
        ksort($datos);
        if ($filas) {
            @mkdir(dirname($this->archivo), 0775, true);
            file_put_contents($this->archivo, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        }

        return count($datos);
    }

    /** Rellena los enclaves sin coordenadas; devuelve cuántos. */
    public function completar(): int
    {
        $n = 0;
        foreach ($this->leer() as $id => $c) {
            $n += $this->db->executeStatement('UPDATE enclave SET latitud = :lat, longitud = :lng WHERE id = :id AND (latitud IS NULL OR longitud IS NULL)', ['lat' => $c['latitud'], 'lng' => $c['longitud'], 'id' => $id]);
        }

        return $n;
    }
}
