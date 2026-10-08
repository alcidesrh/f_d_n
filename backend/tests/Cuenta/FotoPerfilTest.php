<?php

namespace App\Tests\Cuenta;

use App\Cuenta\FotoPerfil;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FotoPerfilTest extends TestCase
{
    private const PNG = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==";

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . "/fdn-fotos-" . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . "/avatares/*") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir . "/avatares");
        @rmdir($this->dir);
    }

    private function usuario(int $id): Usuario
    {
        $u = new Usuario();
        (new \ReflectionProperty(\App\Entity\Base\Base::class, "id"))->setValue($u, $id);

        return $u;
    }

    private function subida(string $contenido, string $nombre): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), "fdn");
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    public function testSinFotoNoHayUrl(): void
    {
        $this->assertNull((new FotoPerfil($this->dir, "s"))->url($this->usuario(1)));
    }

    public function testGuardaLaImagenYReemplazaLaAnterior(): void
    {
        $fotos = new FotoPerfil($this->dir, "s");
        $u = $this->usuario(7);

        $u->setFoto($fotos->guardar($this->subida(base64_decode(self::PNG), "a.png"), $u));
        $primera = $fotos->absoluta($u->getFoto());
        $this->assertFileExists($primera);
        $this->assertStringEndsWith(".png", $u->getFoto());

        $u->setFoto($fotos->guardar($this->subida(base64_decode(self::PNG), "b.png"), $u));
        $this->assertFileDoesNotExist($primera);
        $this->assertFileExists($fotos->absoluta($u->getFoto()));
    }

    public function testRechazaLoQueNoEsImagen(): void
    {
        $this->expectException(\DomainException::class);
        $u = $this->usuario(7);
        (new FotoPerfil($this->dir, "s"))->guardar($this->subida("<?php echo 1;", "x.png"), $u);
    }

    public function testRechazaSvgAunqueSeaImagen(): void
    {
        $this->expectException(\DomainException::class);
        $u = $this->usuario(7);
        (new FotoPerfil($this->dir, "s"))->guardar($this->subida('<svg xmlns="http://www.w3.org/2000/svg"/>', "x.svg"), $u);
    }

    public function testLaFirmaDependeDelUsuarioYDeLaFoto(): void
    {
        $fotos = new FotoPerfil($this->dir, "s");
        $a = $this->usuario(1)->setFoto("avatares/1-aa.png");
        $firma = basename((string) $fotos->url($a));

        $this->assertTrue($fotos->firmaValida($a, $firma));
        $this->assertFalse($fotos->firmaValida($this->usuario(2)->setFoto("avatares/1-aa.png"), $firma));
        $this->assertFalse($fotos->firmaValida($this->usuario(1)->setFoto("avatares/1-bb.png"), $firma));
        $this->assertFalse($fotos->firmaValida($this->usuario(1), $firma));
    }
}
