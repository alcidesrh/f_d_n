<?php

declare(strict_types=1);

namespace App\Tests\Actividad;

use App\Actividad\NivelActividad;
use App\Actividad\Politica;
use App\Actividad\Seleccion;
use App\Actividad\Sujeto;
use App\Actividad\TipoActividad;
use App\Entity\ConfiguracionBitacora;
use PHPUnit\Framework\TestCase;

final class PoliticaTest extends TestCase
{
    public function testPorDefectoNivelMedioATodosConDosMesesDeRetencion(): void
    {
        $c = new ConfiguracionBitacora();
        $p = Politica::desde($c);

        $this->assertTrue($p->activa);
        $this->assertSame(NivelActividad::MEDIO, $p->nivel);
        $this->assertFalse($p->soloSeleccionados);
        $this->assertSame(2, $c->getRetencionMeses());
        $this->assertSame(4096, $p->maxDetalleBytes);
        $this->assertTrue($p->sigue(new Sujeto(1), new \DateTimeImmutable()));
    }

    public function testCadaNivelRegistraLoSuyo(): void
    {
        $minimo = new Politica(nivel: NivelActividad::MINIMO);
        $this->assertNull($minimo->tipoDePeticion("GET", false), "el mínimo no guarda lecturas");
        $this->assertSame(TipoActividad::PETICION, $minimo->tipoDePeticion("POST", false));
        $this->assertNull($minimo->tipoDePeticion("GET", true), "ni consultas automáticas");
        $this->assertFalse($minimo->registra(TipoActividad::PANTALLA));

        $medio = new Politica(nivel: NivelActividad::MEDIO);
        $this->assertSame(TipoActividad::PETICION, $medio->tipoDePeticion("GET", false));
        $this->assertSame(TipoActividad::SONDEO, $medio->tipoDePeticion("GET", true));
        $this->assertFalse($medio->registra(TipoActividad::PANTALLA));
        $this->assertFalse($medio->cuerpos());

        $maximo = new Politica(nivel: NivelActividad::MAXIMO);
        $this->assertTrue($maximo->registra(TipoActividad::PANTALLA));
        $this->assertTrue($maximo->cuerpos());
    }

    public function testUnaFamiliaApagadaNoSeRegistraAunqueElNivelLaIncluya(): void
    {
        $p = new Politica(nivel: NivelActividad::MAXIMO, familiasApagadas: [TipoActividad::SONDEO, TipoActividad::PANTALLA]);

        $this->assertNull($p->tipoDePeticion("GET", true));
        $this->assertFalse($p->registra(TipoActividad::PANTALLA));
        $this->assertTrue($p->registra(TipoActividad::SESION));
    }

    public function testApagadaNoRegistraNada(): void
    {
        $p = new Politica(activa: false, nivel: NivelActividad::MAXIMO);

        foreach (TipoActividad::cases() as $t) {
            $this->assertFalse($p->registra($t));
        }
        $this->assertFalse($p->sigue(new Sujeto(1), new \DateTimeImmutable()));
    }

    public function testSoloSeleccionadosYCuandoHacenFaltaLosPermisos(): void
    {
        $porUsuario = new Politica(soloSeleccionados: true, seleccion: new Seleccion([["tipo" => "usuario", "id" => 5]]));
        $this->assertFalse($porUsuario->necesitaPermisos(), "por usuario o estación no hace falta cargar sus asignaciones");
        $this->assertTrue($porUsuario->sigue(new Sujeto(5), new \DateTimeImmutable()));
        $this->assertFalse($porUsuario->sigue(new Sujeto(6), new \DateTimeImmutable()));

        $porRol = new Politica(soloSeleccionados: true, seleccion: new Seleccion([["tipo" => "rol", "id" => 2]]));
        $this->assertTrue($porRol->necesitaPermisos());
        $this->assertFalse((new Politica(seleccion: new Seleccion([["tipo" => "rol", "id" => 2]])))->necesitaPermisos(), "con alcance «todos» la selección no se mira");
    }

    public function testLosCamposExtraSeSumanALaListaBlanca(): void
    {
        $c = (new ConfiguracionBitacora())->setCamposExtra(["placa", "nit"]);
        $campos = Politica::desde($c)->camposPermitidos();

        $this->assertContains("placa", $campos);
        $this->assertSame(count(array_unique($campos)), count($campos));
        $this->assertNotContains("password", $campos);
    }
}
