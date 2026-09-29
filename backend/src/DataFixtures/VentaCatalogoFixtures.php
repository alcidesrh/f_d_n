<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Moneda;
use App\Entity\TipoDocumento;
use App\Entity\TipoPago;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Catálogos mínimos de la venta para desarrollo. En producción vienen del
 * legado (`app:migrar`: tipo_pago, moneda, tipo_documento).
 */
class VentaCatalogoFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach (["Efectivo", "Tarjeta"] as $nombre) {
            $manager->persist((new TipoPago())->setNombre($nombre));
        }
        foreach ([["GTQ", "Quetzal"], ["USD", "Dólar estadounidense"]] as [$sigla, $nombre]) {
            $manager->persist((new Moneda())->setSigla($sigla)->setNombre($nombre));
        }
        foreach ([["DPI", "Documento Personal de Identificación"], ["PAS", "Pasaporte"]] as [$sigla, $nombre]) {
            $manager->persist((new TipoDocumento())->setSigla($sigla)->setNombre($nombre));
        }
        $manager->flush();
    }
}
