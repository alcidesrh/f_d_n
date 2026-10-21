<?php

declare(strict_types=1);

namespace App\Tests\Ruta;

use App\Entity\BusClase;
use App\Entity\Enclave;
use App\Entity\Parada;
use App\Entity\ParadaTiempo;
use App\Entity\Ruta;
use PHPUnit\Framework\TestCase;

/** Lo que dura una ruta según la clase del bus (ADR-030, ADR-031). */
final class DuracionRutaTest extends TestCase
{
    public function testUsaLosMinutosDeLaClaseYSiNoLosGenerales(): void
    {
        $platino = (new BusClase())->setNombre("Platino");
        $economica = (new BusClase())->setNombre("Económica");
        $oro = (new BusClase())->setNombre("Oro");
        $ruta = new Ruta();
        $ruta->getParadas()->add(new Parada($ruta, self::enclave(), 0));
        $ruta->getParadas()->add($destino = (new Parada($ruta, self::enclave(), 1))->setMinutos(300));
        $destino->getTiempos()->add(new ParadaTiempo($destino, $platino, 240));
        $destino->getTiempos()->add(new ParadaTiempo($destino, $economica, 360));

        $this->assertSame(240, $ruta->duracionPara($platino));
        $this->assertSame(300, $ruta->duracionPara($oro), "sin minutos propios: los generales");
        $this->assertSame(300, $ruta->duracionPara(null), "sin clase: los generales");
        $this->assertSame(360, $ruta->getDuracionMinutos(), "la más lenta");

        $destino->setMinutos(null);
        $this->assertSame(360, $ruta->duracionPara(null), "sin generales: la más lenta");
    }

    private static function enclave(): Enclave
    {
        return new Enclave();
    }
}
