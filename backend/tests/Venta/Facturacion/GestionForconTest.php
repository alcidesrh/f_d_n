<?php

declare(strict_types=1);

namespace App\Tests\Venta\Facturacion;

use App\Entity\ConfiguracionForcon;
use App\Entity\Empresa;
use App\Venta\CifradoCredenciales;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\CredencialesFel;
use App\Venta\Facturacion\Forcon\AjustesForcon;
use App\Venta\Facturacion\Forcon\ClienteForcon;
use App\Venta\Facturacion\Forcon\GestionForcon;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

final class GestionForconTest extends TestCase
{
    private ConfiguracionForcon $config;
    private CifradoCredenciales $cifrado;

    public function testGuardaSoloLoQueVieneYNuncaDevuelveLaClave(): void
    {
        $gestion = $this->gestion();
        $gestion->guardar(["activo" => true, "usuario" => "usr", "clave" => "secreta"], null);

        $this->assertTrue($this->config->isActivo());
        $this->assertSame(ConfiguracionForcon::URL_PRUEBAS, $this->config->getUrl());
        $this->assertSame("secreta", $this->cifrado->descifrar(CredencialesFel::CONTEXTO, (string) $this->config->getClaveCifrada()));
        $this->assertNotNull($this->config->getActualizadaEn());

        $datos = $gestion->datos();
        $this->assertTrue($datos["conClave"]);
        $this->assertStringNotContainsString("secreta", (string) json_encode($datos));
        $this->assertStringNotContainsString((string) $this->config->getClaveCifrada(), (string) json_encode($datos));
    }

    public function testClaveVaciaConservaLaGuardadaYUsuarioVacioQuitaLaCredencial(): void
    {
        $gestion = $this->gestion();
        $gestion->guardar(["usuario" => "usr", "clave" => "secreta"], null);
        $cifrada = $this->config->getClaveCifrada();

        $gestion->guardar(["usuario" => "otro", "clave" => ""], null);
        $this->assertSame("otro", $this->config->getUsuario());
        $this->assertSame($cifrada, $this->config->getClaveCifrada());

        $gestion->guardar(["usuario" => ""], null);
        $this->assertNull($this->config->getUsuario());
        $this->assertNull($this->config->getClaveCifrada());
    }

    public function testUsuarioNuevoSinClaveSeRechaza(): void
    {
        $this->expectException(VentaRechazada::class);
        $this->gestion()->guardar(["usuario" => "usr"], null);
    }

    public function testUrlSoloHttps(): void
    {
        $this->expectException(VentaRechazada::class);
        $this->gestion()->guardar(["url" => "http://pruebasfel.eforcon.com"], null);
    }

    public function testEmisoresEnLineaConElNitDeCadaEmpresa(): void
    {
        $this->gestion()->guardar(["emisoresEnLinea" => [["empresa" => "20336837", "emisor" => " 6907303-1 "]]], null);

        $this->assertSame(["2033683-7" => "6907303-1"], $this->config->getEmisoresEnLinea());
    }

    /** @return iterable<string, array{list<array<string, string>>}> */
    public static function emisoresInvalidos(): iterable
    {
        yield "empresa inexistente" => [[["empresa" => "999", "emisor" => "6907303-1"]]];
        yield "a nombre de sí misma" => [[["empresa" => "2033683-7", "emisor" => "20336837"]]];
        yield "repetida" => [[["empresa" => "2033683-7", "emisor" => "6907303-1"], ["empresa" => "2033683-7", "emisor" => "4397700-6"]]];
    }

    /** @param list<array<string, string>> $lista */
    #[\PHPUnit\Framework\Attributes\DataProvider("emisoresInvalidos")]
    public function testEmisoresInvalidos(array $lista): void
    {
        $this->expectException(VentaRechazada::class);
        $this->gestion()->guardar(["emisoresEnLinea" => $lista], null);
    }

    private function gestion(): GestionForcon
    {
        $this->config = new ConfiguracionForcon();
        $this->cifrado = new CifradoCredenciales("secreto-de-prueba");
        $empresas = [
            (new Empresa())->setNombre("La Pionera")->setNit("2033683-7"),
            (new Empresa())->setNombre("Maya de Oro")->setNit("4397700-6"),
            (new Empresa())->setNombre("Rosita")->setNit("6907303-1"),
        ];
        $repo = $this->createStub(EntityRepository::class);
        $repo->method("findBy")->willReturn($empresas);
        $repo->method("findAll")->willReturn([]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method("find")->willReturn($this->config);
        $em->method("getRepository")->willReturn($repo);
        $ajustes = AjustesForcon::fijos($this->config);

        return new GestionForcon(
            $em,
            $ajustes,
            $this->cifrado,
            new MockClock("2026-10-31 12:00:00"),
            new ClienteForcon(new MockHttpClient(), $ajustes, new CredencialesFel($em, $this->cifrado, $ajustes)),
        );
    }
}
