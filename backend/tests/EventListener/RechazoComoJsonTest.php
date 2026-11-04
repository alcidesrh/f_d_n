<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\RechazoComoJson;
use App\Salida\SalidaRechazada;
use App\Venta\Excepcion\VentaRechazada;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

final class RechazoComoJsonTest extends TestCase
{
    public function testRespondeElRechazoConSuEstadoYDetalle(): void
    {
        $evento = $this->evento(new VentaRechazada("Asiento ocupado.", "asiento_ocupado", 409, ["asientos" => [3]]));

        (new RechazoComoJson(new Serializer([], [new JsonEncoder()])))($evento);

        $respuesta = $evento->getResponse();
        $this->assertNotNull($respuesta);
        $this->assertSame(409, $respuesta->getStatusCode());
        $this->assertSame(["error" => "Asiento ocupado.", "codigo" => "asiento_ocupado", "asientos" => [3]], json_decode((string) $respuesta->getContent(), true));
        $this->assertTrue($evento->isPropagationStopped(), "no llega al registro de errores");
    }

    public function testCadaRechazoTraeSuCodigoPorDefecto(): void
    {
        $evento = $this->evento(new SalidaRechazada("No."));

        (new RechazoComoJson(new Serializer([], [new JsonEncoder()])))($evento);

        $this->assertSame(422, $evento->getResponse()?->getStatusCode());
        $this->assertSame(["error" => "No.", "codigo" => "salida_invalida"], json_decode((string) $evento->getResponse()?->getContent(), true));
    }

    public function testOtrasExcepcionesSiguenSuCamino(): void
    {
        $evento = $this->evento(new \RuntimeException("fallo"));

        (new RechazoComoJson(new Serializer([], [new JsonEncoder()])))($evento);

        $this->assertNull($evento->getResponse());
    }

    private function evento(\Throwable $e): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $e);
    }
}
