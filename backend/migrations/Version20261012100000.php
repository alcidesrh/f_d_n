<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Coordenadas reales (WGS84) de los enclaves de la ruta Guatemala – Petén,
 * tomadas de coordenadas_guatemala_agent.json. Pisa las que tuvieran y, por el
 * mismo lugar, también las terminales hermanas (StarBus, "El Chal").
 */
final class Version20261012100000 extends AbstractMigration
{
    /** nombre del enclave => [lat, lng] */
    private const COORDENADAS = [
        'Sanarate' => [14.795, -90.192],
        'El Progreso, Guastatoya' => [14.91713, -90.0],
        'Teculutan, Zacapa' => [15.05422, -89.75475],
        'Zacapa' => [14.97138, -89.46475],
        'Rio Hondo' => [15.09526, -89.58998],
        'Gualan, Zacapa' => [15.11952, -89.35721],
        'Mayuelas Gualan StarBus' => [15.11952, -89.35721],
        'Los Amates, Izabal' => [15.256, -89.097],
        'Los Amates Starbus' => [15.256, -89.097],
        'El Estor' => [15.53333, -89.35],
        'El Estor StarBus' => [15.53333, -89.35],
        'Cadenas, Modesto Mendez' => [15.89521, -89.22934],
        'Chacte, Peten' => [16.0, -89.5],
        'San Luis' => [16.13671, -89.47948],
        'Poptun, Peten' => [16.331, -89.416],
        'Dolores, Peten' => [16.5118, -89.4161],
        'Turicentro Santa Isabel' => [16.6419726, -89.6338564],
        'Chal, Peten' => [16.6424389, -89.651321],
        'El Chal' => [16.6424389, -89.651321],
        'Chal Santa Ana' => [16.77305, -89.66878],
        'Cruce de San Juan' => [16.6332692, -89.6060104],
    ];

    public function getDescription(): string
    {
        return 'Coordenadas de los enclaves Guatemala – Petén';
    }

    public function up(Schema $schema): void
    {
        foreach (self::COORDENADAS as $nombre => [$lat, $lng]) {
            $this->addSql('UPDATE enclave SET latitud = :lat, longitud = :lng WHERE nombre = :nombre', ['lat' => $lat, 'lng' => $lng, 'nombre' => $nombre]);
        }
    }

    public function down(Schema $schema): void
    {
        // Las coordenadas previas no se conservan.
    }
}
