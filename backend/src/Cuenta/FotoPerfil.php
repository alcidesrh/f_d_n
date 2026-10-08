<?php

declare(strict_types=1);

namespace App\Cuenta;

use App\Entity\Usuario;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Foto de perfil de los usuarios, en `<CHAT_ARCHIVOS_DIR>/avatares` (el mismo
 * volumen persistente del chat). Solo imágenes, reconocidas por contenido.
 * Se sirve por una URL firmada y estable: lleva la ruta del archivo, así que
 * al cambiar la foto cambia la URL y el navegador no muestra la anterior.
 */
final class FotoPerfil
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    private const SUBDIRECTORIO = "avatares";
    private const IMAGENES = [IMAGETYPE_JPEG => "jpg", IMAGETYPE_PNG => "png", IMAGETYPE_WEBP => "webp"];
    private const MIME = ["jpg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];

    public function __construct(
        #[Autowire(env: "CHAT_ARCHIVOS_DIR")] private readonly string $directorio,
        #[Autowire("%kernel.secret%")] private readonly string $secreto,
    ) {}

    /** Guarda la foto, borra la anterior y devuelve la ruta relativa guardada. */
    public function guardar(UploadedFile $archivo, Usuario $usuario): string
    {
        if (!$archivo->isValid()) {
            throw new \DomainException("No se pudo recibir la imagen.");
        }
        $tamano = (int) $archivo->getSize();
        if ($tamano <= 0 || $tamano > self::MAX_BYTES) {
            throw new \DomainException("La imagen es demasiado grande (máximo 2 MB).");
        }
        $imagen = @getimagesize($archivo->getPathname());
        $extension = $imagen !== false ? (self::IMAGENES[$imagen[2]] ?? null) : null;
        if ($extension === null) {
            throw new \DomainException("Formato no permitido: use una imagen JPG, PNG o WebP.");
        }

        $ruta = sprintf("%s/%d-%s.%s", self::SUBDIRECTORIO, $usuario->getId(), bin2hex(random_bytes(8)), $extension);
        $destino = $this->absoluta($ruta);
        if (!is_dir(dirname($destino)) && !mkdir(dirname($destino), 0750, true) && !is_dir(dirname($destino))) {
            throw new \RuntimeException("No se pudo guardar la imagen.");
        }
        $archivo->move(dirname($destino), basename($destino));
        $this->borrar($usuario);

        return $ruta;
    }

    /** Borra del disco la foto actual del usuario (no toca la entidad). */
    public function borrar(Usuario $usuario): void
    {
        $ruta = $usuario->getFoto();
        if ($ruta !== null && is_file($archivo = $this->absoluta($ruta))) {
            @unlink($archivo);
        }
    }

    public function absoluta(string $ruta): string
    {
        return rtrim($this->directorio, "/") . "/" . $ruta;
    }

    public function mime(string $ruta): string
    {
        return self::MIME[strtolower(pathinfo($ruta, PATHINFO_EXTENSION))] ?? "application/octet-stream";
    }

    /** URL relativa a la API (`/usuarios/{id}/foto/{firma}`) o null si no tiene foto. */
    public function url(Usuario $usuario): ?string
    {
        $ruta = $usuario->getFoto();

        return $ruta === null ? null : sprintf("/usuarios/%d/foto/%s", $usuario->getId(), $this->firma((int) $usuario->getId(), $ruta));
    }

    public function firmaValida(Usuario $usuario, string $firma): bool
    {
        $ruta = $usuario->getFoto();

        return $ruta !== null && hash_equals($this->firma((int) $usuario->getId(), $ruta), $firma);
    }

    private function firma(int $id, string $ruta): string
    {
        return substr(hash_hmac("sha256", "usuario-foto:$id:$ruta", $this->secreto), 0, 32);
    }
}
