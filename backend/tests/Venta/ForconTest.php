<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Entity\Empresa;
use App\Venta\CifradoCredenciales;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\CredencialesFel;
use App\Venta\Facturacion\Forcon\DatosEmisorForcon;
use App\Venta\Facturacion\Forcon\DteJsonForcon;
use App\Venta\Facturacion\ItemDte;
use App\Venta\Facturacion\SolicitudDte;
use Doctrine\ORM\EntityManagerInterface;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class ForconTest extends TestCase
{
    /** Copia de `docs/certificador_factura_electronica/Ejemplos JSON Esquema SAT/5. Factura (local).json`. */
    private const EJEMPLO = __DIR__ . "/fixtures/forcon_factura_local.json";

    private static function solicitud(?int $numeroAcceso = null): SolicitudDte
    {
        return new SolicitudDte(
            referenciaInterna: "FDN-VENTA-42",
            fechaEmision: new \DateTimeImmutable("2026-09-27T09:52:00.000-06:00"),
            emisorNit: "43977006",
            emisorNombre: "AUTOBUSES MAYA DE ORO, SOCIEDAD ANONIMA",
            emisorDireccion: null,
            establecimiento: "Aguilar Batres",
            receptorNit: "28119266",
            receptorNombre: "BAUTISTA OROZCO, JENNER OSWALDO",
            receptorCorreo: null,
            items: [
                new ItemDte("Boleto Guatemala - San Marcos, asiento 31", 1, Money::GTQ(10000), Money::GTQ(10000), 31, "JENNER BAUTISTA"),
                new ItemDte("Boleto Guatemala - San Marcos, asiento 32", 1, Money::GTQ(8925), Money::GTQ(8925), 32, null),
            ],
            total: Money::GTQ(18925),
            establecimientoCodigo: 3,
            afiliacionIva: "GEN",
            frases: [[1, 1], [2, 1]],
            numeroAcceso: $numeroAcceso,
            ruta: "Guatemala - San Marcos",
        );
    }

    private static function emisor(): array
    {
        return DatosEmisorForcon::desdeRespuesta([
            "NombreComercial" => "MAYA DE ORO", "CorreoElectronico" => "fel@example.com",
            "DireccionCompletaAutomatica" => "CALZ. AGUILAR BATRES 7-55 ZONA 12", "CodigoPostal" => "01012",
            "Municipio" => "Guatemala", "Departamento" => "Guatemala", "Pais" => "GT",
            "RazonSocial" => "AUTOBUSES MAYA DE ORO, SOCIEDAD ANONIMA",
        ]);
    }

    /** Mismas claves que el ejemplo oficial (salvo lo opcional). */
    public function testEstructuraIgualAlEjemploDeForcon(): void
    {
        $ejemplo = json_decode((string) file_get_contents(self::EJEMPLO), true)["DatosEmision"];
        $dte = DteJsonForcon::construir(self::solicitud(), self::emisor())["DatosEmision"];

        $this->assertSame(array_keys($ejemplo["DatosGeneralesEmision"]), array_keys($dte["DatosGeneralesEmision"]));
        $this->assertEqualsCanonicalizing(array_keys($ejemplo["Emisor"]), array_keys($dte["Emisor"]));
        $this->assertEqualsCanonicalizing(array_keys($ejemplo["Emisor"]["DireccionEmisor"]), array_keys($dte["Emisor"]["DireccionEmisor"]));
        $this->assertEqualsCanonicalizing(
            array_diff(array_keys($ejemplo["Receptor"]), ["DireccionReceptor"]),
            array_keys($dte["Receptor"]),
        );
        $this->assertEqualsCanonicalizing(array_keys($ejemplo["Items"]["Item"][0]), array_keys($dte["Items"]["Item"][0]));
        $this->assertEqualsCanonicalizing(array_keys($ejemplo["Totales"]), array_keys($dte["Totales"]));
    }

    public function testImportesConIvaIncluido(): void
    {
        $dte = DteJsonForcon::construir(self::solicitud(), self::emisor())["DatosEmision"];
        $item = $dte["Items"]["Item"][0];
        $impuesto = $item["Impuestos"]["Impuesto"][0];

        $this->assertSame("S", $item["BienOServicio"]);
        $this->assertSame("100.00", $item["Total"]);
        $this->assertSame("89.285714", $impuesto["MontoGravable"]);
        $this->assertSame("10.714286", $impuesto["MontoImpuesto"]);
        $this->assertEqualsWithDelta(100.0, (float) $impuesto["MontoGravable"] + (float) $impuesto["MontoImpuesto"], 0.000001);
        $this->assertSame("189.25", $dte["Totales"]["GranTotal"]);
        $this->assertSame("2026-09-27T09:52:00.000-06:00", $dte["DatosGeneralesEmision"]["FechaHoraEmision"]);
        $this->assertSame("3", $dte["Emisor"]["CodigoEstablecimiento"]);
        $this->assertSame([["CodigoEscenario" => "1", "TipoFrase" => "1"], ["CodigoEscenario" => "1", "TipoFrase" => "2"]], $dte["Frases"]["Frase"]);
    }

    public function testAdendaLlevaCodigoInternoAsientoYPasajero(): void
    {
        $adenda = DteJsonForcon::construir(self::solicitud(), self::emisor())["DatosEmision"]["Adenda"];

        $this->assertContains(["CodigoEtiqueta" => "59", "ValorEtiqueta" => "FDN-VENTA-42"], $adenda["Encabezado"]["DefinicionEncabezado"]);
        $this->assertContains(["NumeroItem" => "1", "CodigoEtiqueta" => "129", "ValorEtiqueta" => "31"], $adenda["Detalle"]["DefinicionDetalle"]);
        $this->assertContains(["NumeroItem" => "1", "CodigoEtiqueta" => "132", "ValorEtiqueta" => "JENNER BAUTISTA"], $adenda["Detalle"]["DefinicionDetalle"]);
    }

    public function testContingenciaLlevaNumeroDeAcceso(): void
    {
        $this->assertArrayNotHasKey("NumeroAcceso", DteJsonForcon::construir(self::solicitud(), self::emisor())["DatosEmision"]["DatosGeneralesEmision"]);
        $this->assertSame("123456789", DteJsonForcon::construir(self::solicitud(123456789), self::emisor())["DatosEmision"]["DatosGeneralesEmision"]["NumeroAcceso"]);
    }

    public function testConsumidorFinal(): void
    {
        $s = self::solicitud();
        $cf = new SolicitudDte($s->referenciaInterna, $s->fechaEmision, $s->emisorNit, $s->emisorNombre, null, null, "CF", "Juan", null, $s->items, $s->total);

        $this->assertSame("CONSUMIDOR FINAL", DteJsonForcon::construir($cf, self::emisor())["DatosEmision"]["Receptor"]["NombreReceptor"]);
    }

    /** Respuesta exitosa del documento técnico (con la errata `FechaCertifcacionDTE`). */
    public function testRespuestaCertificada(): void
    {
        $dte = DteJsonForcon::respuesta([
            "StatusCode" => "OK", "Resultado" => true, "Descripcion" => "DTE certificado exitosamente!",
            "AutorizacionUUID" => "90c5a7e5-54e9-4bf1-a5f8-47cc8f4b5a4", "NumeroDTE" => "1424576473", "SerieDTE" => "90C5A7E5",
            "FechaEmisionDTE" => "2021-10-30T12:37:49.463-06:00", "FechaCertifcacionDTE" => "2021-10-28T10:52:30.222-06:00",
            "XMLCertificado" => "<xml/>", "RutaPDF" => "http://pruebasfel.eforcon.com/pdf", "GranTotal" => 9513.17,
        ]);

        $this->assertSame(1424576473, $dte->numero);
        $this->assertSame("90C5A7E5", $dte->serie);
        $this->assertSame("2021-10-28T10:52:30-06:00", $dte->fechaCertificacion->format(DATE_ATOM));
        $this->assertSame("http://pruebasfel.eforcon.com/pdf", $dte->urlPdf);
        $this->assertSame("4150686", $dte->certificadorNit);
    }

    public function testRechazosDelDocumentoNoSonRecuperables(): void
    {
        try {
            DteJsonForcon::respuesta(["StatusCode" => "BadRequest", "Resultado" => false, "Descripcion" => "ERROR EVI-049: El NIT del Receptor ingresado no es válido"]);
            $this->fail("debía fallar");
        } catch (CertificacionFallida $e) {
            $this->assertFalse($e->recuperable);
            $this->assertSame("EVI-049", $e->codigo);
            $this->assertStringContainsString("NIT del cliente", $e->getMessage());
        }

        $duplicado = DteJsonForcon::fallo("ERROR ESW-025: El Código Interno incluido en el DTE, fue certificado previamente");
        $this->assertSame("ESW-025", $duplicado->codigo);
        $this->assertFalse($duplicado->recuperable);

        $sinCodigo = DteJsonForcon::fallo("Servicio no disponible");
        $this->assertTrue($sinCodigo->recuperable);
    }

    public function testCredencialesCifradasIdaYVuelta(): void
    {
        $credenciales = new CredencialesFel($this->createStub(EntityManagerInterface::class), new CifradoCredenciales("secreto-de-prueba"));
        $cifrada = $credenciales->cifrar("clave-forcon");

        $this->assertStringNotContainsString("clave-forcon", $cifrada);
        $this->assertSame("clave-forcon", $credenciales->descifrar($cifrada));

        $otra = new CredencialesFel($this->createStub(EntityManagerInterface::class), new CifradoCredenciales("otro-secreto"));
        $this->expectException(CertificacionFallida::class);
        $otra->descifrar($cifrada);
    }

    public function testFrasesDeLaEmpresa(): void
    {
        $empresa = (new Empresa())->setFrasesFel("1-1, 2-1,x");

        $this->assertSame([[1, 1], [2, 1]], $empresa->frases());
    }

    public function testCertificadorForconContraApiSimulada(): void
    {
        $pedidos = [];
        $http = new \Symfony\Component\HttpClient\MockHttpClient(function (string $metodo, string $url, array $opciones) use (&$pedidos) {
            $pedidos[] = [$metodo, $url, $opciones];
            $cuerpo = str_contains($url, "/apidatosemisor/")
                ? ["StatusCode" => "OK", "Resultado" => true, "NombreComercial" => "MAYA DE ORO", "CorreoElectronico" => "fel@example.com",
                    "DireccionCompletaAutomatica" => "CALZ. AGUILAR BATRES", "CodigoPostal" => "01012", "Municipio" => "Guatemala",
                    "Departamento" => "Guatemala", "Pais" => "GT", "RazonSocial" => "AUTOBUSES MAYA DE ORO, S.A."]
                : ["StatusCode" => "OK", "Resultado" => true, "AutorizacionUUID" => "AAAA0000-0000-0000-0000-000000000001",
                    "NumeroDTE" => "935022181", "SerieDTE" => "6BF69EA1", "FechaCertifcacionDTE" => "2026-09-27T09:52:00-06:00"];

            return new \Symfony\Component\HttpClient\Response\MockResponse(json_encode($cuerpo));
        });
        $credenciales = new CredencialesFel($this->createStub(EntityManagerInterface::class), new CifradoCredenciales("s"), "usr", "pwd");
        $cliente = new \App\Venta\Facturacion\Forcon\ClienteForcon($http, "https://pruebasfel.eforcon.com", $credenciales);
        $certificador = new \App\Venta\Facturacion\Forcon\CertificadorForcon(
            $cliente,
            new DatosEmisorForcon($cliente, new \Symfony\Component\Cache\Adapter\ArrayAdapter()),
        );

        $dte = $certificador->certificar(self::solicitud());

        $this->assertSame(935022181, $dte->numero);
        $this->assertCount(2, $pedidos);
        [$metodo, $url, $opciones] = $pedidos[1];
        $this->assertSame("POST", $metodo);
        $this->assertSame("https://pruebasfel.eforcon.com/apiforcon/fel/EmitirDteJson", $url);
        $this->assertContains("Authorization: Basic " . base64_encode("usr:pwd"), $opciones["headers"]);
        $enviado = json_decode($opciones["body"], true);
        $this->assertSame("MAYA DE ORO", $enviado["DatosEmision"]["Emisor"]["NombreComercial"]);
        $this->assertStringContainsString("NIT=43977006", $pedidos[0][1]);
    }
}
