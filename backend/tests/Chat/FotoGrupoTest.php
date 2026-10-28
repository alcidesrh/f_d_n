<?php

namespace App\Tests\Chat;

use App\Chat\FotoGrupo;
use App\Cuenta\FotoPerfil;
use App\Entity\ChatCanal;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FotoGrupoTest extends TestCase
{
    private const PNG = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==";

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . "/fdn-grupos-" . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . "/grupos/*") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir . "/grupos");
        @rmdir($this->dir);
    }

    private function canal(int $id): ChatCanal
    {
        $canal = ChatCanal::grupo("Coordinación", new Usuario(), []);
        (new \ReflectionProperty(ChatCanal::class, "id"))->setValue($canal, $id);

        return $canal;
    }

    private function subida(string $contenido, string $nombre): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), "fdn");
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    private function servicio(): FotoGrupo
    {
        return new FotoGrupo(new FotoPerfil($this->dir, "s"), "s");
    }

    public function testSinFotoNoHayUrl(): void
    {
        $this->assertNull($this->servicio()->url($this->canal(3)));
    }

    public function testGuardaLaImagenYReemplazaLaAnterior(): void
    {
        $fotos = $this->servicio();
        $canal = $this->canal(3);

        $canal->setFoto($fotos->guardar($this->subida(base64_decode(self::PNG), "a.png"), $canal));
        $primera = $fotos->absoluta($canal->getFoto());
        $this->assertStringStartsWith("grupos/3-", $canal->getFoto());
        $this->assertFileExists($primera);

        $canal->setFoto($fotos->guardar($this->subida(base64_decode(self::PNG), "b.png"), $canal));
        $this->assertFileDoesNotExist($primera);
        $this->assertFileExists($fotos->absoluta($canal->getFoto()));
    }

    public function testQuitarBorraLaFoto(): void
    {
        $fotos = $this->servicio();
        $canal = $this->canal(3);
        $canal->setFoto($fotos->guardar($this->subida(base64_decode(self::PNG), "a.png"), $canal));
        $ruta = $fotos->absoluta($canal->getFoto());

        $fotos->quitar($canal);

        $this->assertNull($canal->getFoto());
        $this->assertFileDoesNotExist($ruta);
    }

    public function testLaFirmaDependeDelCanalYDeLaFoto(): void
    {
        $fotos = $this->servicio();
        $a = $this->canal(1);
        $a->setFoto("grupos/1-aa.png");
        $firma = basename((string) $fotos->url($a));

        $otroCanal = $this->canal(2);
        $otroCanal->setFoto("grupos/1-aa.png");
        $otraFoto = $this->canal(1);
        $otraFoto->setFoto("grupos/1-bb.png");

        $this->assertTrue($fotos->firmaValida($a, $firma));
        $this->assertFalse($fotos->firmaValida($otroCanal, $firma));
        $this->assertFalse($fotos->firmaValida($otraFoto, $firma));
        $this->assertFalse($fotos->firmaValida($this->canal(1), $firma));
    }
}
