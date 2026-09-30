<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Entity\CredencialPago;
use App\Entity\Empresa;
use App\Venta\CifradoCredenciales;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Pago\Continuacion;
use App\Venta\Pago\Cybersource\ClienteCybersource;
use App\Venta\Pago\Cybersource\Comercio;
use App\Venta\Pago\Cybersource\CredencialesCybersource;
use App\Venta\Pago\Cybersource\PasarelaCybersource;
use App\Venta\Pago\DireccionFacturacion;
use App\Venta\Pago\Navegador;
use App\Venta\Pago\PagoIncierto;
use App\Venta\Pago\ResultadoPago;
use App\Venta\Pago\SolicitudPago;
use App\Venta\Pago\Tarjeta;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CybersourceTest extends TestCase
{
    private const SECRETO = "c2VjcmV0by1kZS1wcnVlYmEtY3liZXJzb3VyY2U=";

    /** @var list<array{metodo: string, url: string, cuerpo: array<string, mixed>, encabezados: array<string, list<string>>}> */
    private array $peticiones = [];

    public function testFirmaHttpSignature(): void
    {
        $comercio = new Comercio("fdn_test", "llave-1", self::SECRETO);
        $cuerpo = '{"a":1}';
        $h = ClienteCybersource::firmar($comercio, "apitest.cybersource.com", "POST", "/pts/v2/payments", "Wed, 30 Sep 2026 18:00:00 GMT", $cuerpo);

        $digest = "SHA-256=" . base64_encode(hash("sha256", $cuerpo, true));
        $esperada = base64_encode(hash_hmac(
            "sha256",
            "host: apitest.cybersource.com\ndate: Wed, 30 Sep 2026 18:00:00 GMT\nrequest-target: post /pts/v2/payments\ndigest: {$digest}\nv-c-merchant-id: fdn_test",
            base64_decode(self::SECRETO),
            true,
        ));
        self::assertSame($digest, $h["Digest"]);
        self::assertSame("fdn_test", $h["v-c-merchant-id"]);
        self::assertSame(
            sprintf('keyid="llave-1", algorithm="HmacSHA256", headers="host date request-target digest v-c-merchant-id", signature="%s"', $esperada),
            $h["Signature"],
        );
    }

    public function testImporteEnDecimales(): void
    {
        self::assertSame(["totalAmount" => "150.05", "currency" => "GTQ"], PasarelaCybersource::importe(Money::GTQ(15005)));
        self::assertSame("0.99", PasarelaCybersource::importe(Money::GTQ(99))["totalAmount"]);
    }

    public function testCobroSinDesafio(): void
    {
        $pasarela = $this->pasarela([
            [201, ["status" => "COMPLETED", "consumerAuthenticationInformation" => [
                "accessToken" => "jwt-ddc",
                "referenceId" => "ref-1",
                "deviceDataCollectionUrl" => "https://centinelapistag.cardinalcommerce.com/V1/Cruise/Collect",
            ]]],
            [201, ["id" => "7001", "status" => "AUTHORIZED", "processorInformation" => ["approvalCode" => "831000"],
                "consumerAuthenticationInformation" => ["cavv" => "AAABCSIIAAAAAAACcwgAEMCoNh+=", "eciRaw" => "05"]]],
        ]);
        $s = $this->solicitud();

        $r = $pasarela->cobrar($s);
        self::assertSame(ResultadoPago::DISPOSITIVO, $r->estado);
        self::assertSame(["JWT" => "jwt-ddc"], $r->campos);
        self::assertContains("https://centinelapistag.cardinalcommerce.com", $r->origenes);
        self::assertSame(["paso" => "enrolar", "referencia" => "ref-1"], $r->estadoPasarela);
        self::assertStringEndsWith("/risk/v1/authentication-setups", $this->peticiones[0]["url"]);
        self::assertSame("001", $this->peticiones[0]["cuerpo"]["paymentInformation"]["card"]["type"]);

        $r = $pasarela->cobrar($s, new Continuacion($r->estadoPasarela));
        self::assertSame(ResultadoPago::APROBADO, $r->estado);
        self::assertSame("7001", $r->referenciaPasarela);
        self::assertSame("831000", $r->autorizacion);

        $pago = $this->peticiones[1]["cuerpo"];
        self::assertSame(["CONSUMER_AUTHENTICATION"], $pago["processingInformation"]["actionList"]);
        self::assertTrue($pago["processingInformation"]["capture"]);
        self::assertSame("ref-1", $pago["consumerAuthenticationInformation"]["referenceId"]);
        self::assertSame("https://fdn.test/api/publico/pagos/retorno", $pago["consumerAuthenticationInformation"]["returnUrl"]);
        self::assertSame(["totalAmount" => "250.00", "currency" => "GTQ"], $pago["orderInformation"]["amountDetails"]);
        self::assertSame("Juana María", $pago["orderInformation"]["billTo"]["firstName"]);
        self::assertSame("Pérez", $pago["orderInformation"]["billTo"]["lastName"]);
        self::assertSame("GT", $pago["orderInformation"]["billTo"]["country"]);
        self::assertSame("1920", $pago["deviceInformation"]["httpBrowserScreenWidth"]);
        self::assertSame("es-GT", $pago["deviceInformation"]["httpBrowserLanguage"]);
        self::assertSame(["fdn_test"], $this->peticiones[1]["encabezados"]["v-c-merchant-id"]);
    }

    public function testDesafioYValidacion(): void
    {
        $pareq = base64_encode((string) json_encode(["challengeWindowSize" => "02"]));
        $pasarela = $this->pasarela([
            [201, ["id" => "7002", "status" => "PENDING_AUTHENTICATION", "consumerAuthenticationInformation" => [
                "stepUpUrl" => "https://centinelapistag.cardinalcommerce.com/V2/Cruise/StepUp",
                "accessToken" => "jwt-acs",
                "authenticationTransactionId" => "tx-9",
                "pareq" => $pareq,
            ]]],
            [201, ["id" => "7003", "status" => "AUTHORIZED", "processorInformation" => ["approvalCode" => "123456"],
                "consumerAuthenticationInformation" => ["ucafAuthenticationData" => "kKFA", "paresStatus" => "Y"]]],
        ]);
        $s = $this->solicitud();

        $r = $pasarela->cobrar($s, new Continuacion(["paso" => "enrolar", "referencia" => "ref-1"]));
        self::assertSame(ResultadoPago::AUTENTICACION, $r->estado);
        self::assertSame("https://centinelapistag.cardinalcommerce.com/V2/Cruise/StepUp", $r->url);
        self::assertSame(["JWT" => "jwt-acs"], $r->campos);
        self::assertSame(["390px", "400px"], [$r->ancho, $r->alto]);
        self::assertSame(["paso" => "validar", "transaccion" => "tx-9"], $r->estadoPasarela);

        $r = $pasarela->cobrar($s, new Continuacion($r->estadoPasarela, ["TransactionId" => "x"]));
        self::assertSame(ResultadoPago::APROBADO, $r->estado);
        self::assertSame("7003", $r->referenciaPasarela);
        $validar = $this->peticiones[1]["cuerpo"];
        self::assertSame(["VALIDATE_CONSUMER_AUTHENTICATION"], $validar["processingInformation"]["actionList"]);
        self::assertSame("tx-9", $validar["consumerAuthenticationInformation"]["authenticationTransactionId"]);
    }

    public function testAutorizadoSinAutenticacionSeAnula(): void
    {
        $pasarela = $this->pasarela([
            [201, ["id" => "7004", "status" => "AUTHORIZED", "consumerAuthenticationInformation" => ["cardEnrolled" => "U"]]],
            [201, ["id" => "8001", "status" => "VOIDED"]],
        ]);

        $r = $pasarela->cobrar($this->solicitud(), new Continuacion(["paso" => "validar", "transaccion" => "tx"]));
        self::assertSame(ResultadoPago::RECHAZADO, $r->estado);
        self::assertStringContainsString("No se realizó ningún cobro", (string) $r->mensaje);
        self::assertStringEndsWith("/pts/v2/payments/7004/voids", $this->peticiones[1]["url"]);
    }

    public function testRechazoConMotivoDelBanco(): void
    {
        $pasarela = $this->pasarela([
            [201, ["id" => "7005", "status" => "DECLINED", "errorInformation" => ["reason" => "INSUFFICIENT_FUND", "message" => "Decline - Insufficient funds"]]],
        ]);

        $r = $pasarela->cobrar($this->solicitud(), new Continuacion(["paso" => "enrolar", "referencia" => "r"]));
        self::assertSame(ResultadoPago::RECHAZADO, $r->estado);
        self::assertSame("Su banco rechazó el pago: fondos insuficientes. (código INSUFFICIENT_FUND)", $r->mensaje);
        self::assertCount(1, $this->peticiones, "un rechazo no se anula");
    }

    public function testSinRespuestaAlCobrarEsIncierto(): void
    {
        $pasarela = $this->pasarela([new MockResponse("", ["error" => "timeout"])]);

        $this->expectException(PagoIncierto::class);
        $pasarela->cobrar($this->solicitud(), new Continuacion(["paso" => "enrolar", "referencia" => "r"]));
    }

    public function testReembolsoCuandoYaNoSePuedeAnular(): void
    {
        $pasarela = $this->pasarela([
            [400, ["status" => "INVALID_REQUEST", "reason" => "NOT_VOIDABLE"]],
            [201, ["id" => "9001", "status" => "PENDING"]],
        ]);

        $pasarela->reembolsar("7006", Money::GTQ(25000), 1);
        self::assertStringEndsWith("/pts/v2/payments/7006/refunds", $this->peticiones[1]["url"]);
        self::assertSame("250.00", $this->peticiones[1]["cuerpo"]["orderInformation"]["amountDetails"]["totalAmount"]);
    }

    public function testReembolsoRechazadoLanza(): void
    {
        $pasarela = $this->pasarela([
            [400, ["status" => "INVALID_REQUEST", "reason" => "NOT_VOIDABLE"]],
            [400, ["status" => "INVALID_REQUEST", "reason" => "INVALID_DATA"]],
        ]);

        $this->expectException(\RuntimeException::class);
        $pasarela->reembolsar("7007", Money::GTQ(100), 1);
    }

    public function testVentanaDelDesafio(): void
    {
        self::assertSame(["100%", "100%"], PasarelaCybersource::ventana(null));
        self::assertSame(["500px", "600px"], PasarelaCybersource::ventana(base64_encode('{"challengeWindowSize":"03"}')));
    }

    public function testEvidenciaDeAutenticacion(): void
    {
        self::assertTrue(PasarelaCybersource::autenticado(["cavv" => "x"]));
        self::assertTrue(PasarelaCybersource::autenticado(["cardEnrolled" => "N", "authenticationStatus" => "ATTEMPTED"]));
        self::assertFalse(PasarelaCybersource::autenticado(["ucafAuthenticationData" => "x", "paresStatus" => "N"]));
        self::assertFalse(PasarelaCybersource::autenticado([]));
    }

    public function testDireccionDeEstadosUnidosExigeEstadoYCodigoPostal(): void
    {
        $d = DireccionFacturacion::desdeArray(["pais" => "us", "region" => "ca", "ciudad" => "Los Angeles", "direccion" => "1 Main St", "codigoPostal" => "90001"]);
        self::assertSame(["US", "CA"], [$d->pais, $d->region]);

        $this->expectException(VentaRechazada::class);
        DireccionFacturacion::desdeArray(["pais" => "US", "region" => "California", "ciudad" => "LA", "direccion" => "1 Main St", "codigoPostal" => "90001"]);
    }

    /**
     * @param list<array{0: int, 1: array<string, mixed>}|MockResponse> $respuestas
     */
    private function pasarela(array $respuestas): PasarelaCybersource
    {
        $this->peticiones = [];
        $cola = array_map(static fn($r) => $r instanceof MockResponse ? $r : new MockResponse((string) json_encode($r[1]), ["http_code" => $r[0]]), $respuestas);
        $http = new MockHttpClient(function (string $metodo, string $url, array $opciones) use (&$cola): MockResponse {
            $encabezados = [];
            foreach ($opciones["headers"] as $linea) {
                [$k, $v] = explode(": ", $linea, 2);
                $encabezados[strtolower($k)][] = $v;
            }
            $this->peticiones[] = [
                "metodo" => $metodo,
                "url" => $url,
                "cuerpo" => json_decode((string) $opciones["body"], true),
                "encabezados" => $encabezados,
            ];

            return array_shift($cola) ?? throw new \LogicException("Petición inesperada a {$url}");
        });

        $cifrado = new CifradoCredenciales("secreto-app");
        $credencial = new CredencialPago(new Empresa(), "fdn_test", "llave-1", $cifrado->cifrar("fdn-credencial-pago", self::SECRETO));
        $repo = $this->createStub(EntityRepository::class);
        $repo->method("findOneBy")->willReturn($credencial);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method("getRepository")->willReturn($repo);

        return new PasarelaCybersource(
            new ClienteCybersource($http, new MockClock("2026-09-30 18:00:00"), "https://apitest.cybersource.com"),
            new CredencialesCybersource($em, $cifrado),
            new NullLogger(),
        );
    }

    private function solicitud(): SolicitudPago
    {
        return new SolicitudPago(
            referencia: "0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b",
            empresaId: 1,
            monto: Money::GTQ(25000),
            tarjeta: Tarjeta::desdeArray(["numero" => "4111 1111 1111 1111", "expira" => "12/30", "cvv" => "123", "titular" => "Juana María Pérez"], new \DateTimeImmutable("2026-09-30")),
            direccion: DireccionFacturacion::desdeArray(["pais" => "GT", "ciudad" => "Guatemala", "direccion" => "6a avenida 1-23 zona 1"]),
            nombre: "Juana",
            apellido: "Pérez",
            correo: "juana@example.com",
            telefono: "55551234",
            descripcion: "Boletos",
            urlRetorno: "https://fdn.test/api/publico/pagos/retorno",
            navegador: Navegador::de("190.1.2.3", "Mozilla/5.0", "text/html", "es-GT,es;q=0.9", ["anchoPantalla" => 1920, "altoPantalla" => 1080, "profundidadColor" => 24, "diferenciaHoraria" => 360]),
        );
    }
}
