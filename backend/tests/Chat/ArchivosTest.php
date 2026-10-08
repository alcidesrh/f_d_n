<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\Archivos;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ArchivosTest extends TestCase
{
    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        array_map("unlink", $this->temporales);
    }

    private function archivo(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), "chat");
        file_put_contents($ruta, $contenido);
        $this->temporales[] = $ruta;

        return $ruta;
    }

    private function png(int $ancho, int $alto): string
    {
        // PNG mínimo válido: firma + IHDR (getimagesize solo lee la cabecera).
        $ihdr = pack("NNCCCCC", $ancho, $alto, 8, 2, 0, 0, 0);

        return $this->archivo("\x89PNG\r\n\x1a\n" . pack("N", 13) . "IHDR" . $ihdr . pack("N", crc32("IHDR" . $ihdr)));
    }

    public function testReconoceImagenesPorSuContenidoYSusMedidas(): void
    {
        $this->assertSame(["image/png", "png", 640, 480], Archivos::reconocer($this->png(640, 480), "jpg"));
    }

    public function testReconocePdfOfficeYTexto(): void
    {
        $this->assertSame("application/pdf", Archivos::reconocer($this->archivo("%PDF-1.7 ..."), "")[0]);
        $this->assertSame("xlsx", Archivos::reconocer($this->archivo("PK\x03\x04resto"), "xlsx")[1]);
        $this->assertSame("text/csv", Archivos::reconocer($this->archivo("a,b\n1,2"), "csv")[0]);
    }

    public function testRechazaLoQueNoReconoce(): void
    {
        $this->assertNull(Archivos::reconocer($this->archivo("<script>alert(1)</script>"), "html"));
        $this->assertNull(Archivos::reconocer($this->archivo("MZ\x90\x00binario"), "pdf"), "la extensión no basta");
        $this->assertNull(Archivos::reconocer($this->archivo("PK\x03\x04"), "zip"), "solo zip de Office");
        $this->assertNull(Archivos::reconocer($this->archivo("a\0b"), "txt"), "texto con bytes nulos");
    }

    public function testNombreSeguroSinRutasNiControles(): void
    {
        $this->assertSame("boleto.pdf", Archivos::nombreSeguro("../../etc/boleto.pdf"));
        $this->assertSame("foto.jpg", Archivos::nombreSeguro("C:\\fotos\\foto.jpg"));
        $this->assertSame("ab.png", Archivos::nombreSeguro("a\x00<b>.png"));
        $this->assertSame("archivo", Archivos::nombreSeguro("   "));
    }

    public function testLaFirmaVenceYNoSirveParaOtroArchivo(): void
    {
        $archivos = new Archivos($this->createStub(EntityManagerInterface::class), "/tmp", "secreto");
        $ahora = 1_800_000_000;
        $url = (new \ReflectionMethod($archivos, "firma"))->invoke($archivos, 7, $ahora + 3600);

        $this->assertTrue($archivos->firmaValida(7, $url, $ahora + 3600, $ahora));
        $this->assertFalse($archivos->firmaValida(8, $url, $ahora + 3600, $ahora), "otro id");
        $this->assertFalse($archivos->firmaValida(7, $url, $ahora + 7200, $ahora), "otra expiración");
        $this->assertFalse($archivos->firmaValida(7, $url, $ahora + 3600, $ahora + 3601), "vencida");
    }
}
