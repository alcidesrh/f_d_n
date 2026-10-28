<?php

declare(strict_types=1);

namespace App\Chat;

use App\Cuenta\FotoPerfil;
use App\Entity\ChatCanal;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Foto de un grupo del chat, en `<CHAT_ARCHIVOS_DIR>/grupos` (el mismo volumen
 * que las fotos de perfil y los archivos). Se valida y guarda como cualquier
 * foto (`FotoPerfil::archivar`) y se sirve por una URL firmada que lleva la
 * ruta: al cambiarla cambia la URL y el navegador no muestra la anterior.
 */
final class FotoGrupo
{
    private const SUBDIRECTORIO = "grupos";

    public function __construct(
        private readonly FotoPerfil $fotos,
        #[Autowire("%kernel.secret%")] private readonly string $secreto,
    ) {}

    /** Guarda la foto nueva, borra la anterior y devuelve la ruta relativa. */
    public function guardar(UploadedFile $archivo, ChatCanal $canal): string
    {
        $ruta = $this->fotos->archivar($archivo, sprintf("%s/%d", self::SUBDIRECTORIO, $canal->getId()));
        $this->fotos->borra($canal->getFoto());

        return $ruta;
    }

    public function quitar(ChatCanal $canal): void
    {
        $this->fotos->borra($canal->getFoto());
        $canal->setFoto(null);
    }

    /** URL relativa a la API (`/chat/canales/{id}/foto/{firma}`) o null si no tiene foto. */
    public function url(ChatCanal $canal): ?string
    {
        $ruta = $canal->getFoto();

        return $ruta === null ? null : sprintf("/chat/canales/%d/foto/%s", $canal->getId(), $this->firma((int) $canal->getId(), $ruta));
    }

    public function firmaValida(ChatCanal $canal, string $firma): bool
    {
        $ruta = $canal->getFoto();

        return $ruta !== null && hash_equals($this->firma((int) $canal->getId(), $ruta), $firma);
    }

    public function absoluta(string $ruta): string
    {
        return $this->fotos->absoluta($ruta);
    }

    public function mime(string $ruta): string
    {
        return $this->fotos->mime($ruta);
    }

    private function firma(int $id, string $ruta): string
    {
        return substr(hash_hmac("sha256", "canal-foto:$id:$ruta", $this->secreto), 0, 32);
    }
}
