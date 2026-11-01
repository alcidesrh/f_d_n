<?php

declare(strict_types=1);

namespace App\Correo;

use App\Entity\CorreoAdjunto;
use App\Entity\CorreoMensaje;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Archivos de los correos en disco (`CHAT_ARCHIVOS_DIR/correo`). Se sirven
 * por una URL firmada que vence, como los del chat (una `<img>` del HTML del
 * correo no lleva el Bearer). Lo que llega de fuera se guarda con el tipo
 * que declara, pero nunca se muestra en línea salvo imágenes y PDF (y con
 * `nosniff` y CSP `sandbox`): lo demás se descarga.
 */
final class Adjuntos
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    /** Lo que puede llevar un correo que se envía (el proveedor admite algo más). */
    public const MAX_TOTAL = 25 * 1024 * 1024;
    private const VENTANA = 6 * 3600;
    private const VIGENCIA_VENTANAS = 5;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(env: "CHAT_ARCHIVOS_DIR")] private readonly string $directorio,
        #[Autowire("%kernel.secret%")] private readonly string $secreto,
    ) {}

    /** Un archivo subido al borrador. */
    public function subir(CorreoMensaje $borrador, UploadedFile $archivo): CorreoAdjunto
    {
        if (!$archivo->isValid()) {
            throw new CorreoRechazado(in_array($archivo->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? "El archivo es demasiado grande (máximo 10 MB)." : "No se pudo recibir el archivo.");
        }
        $tamano = (int) $archivo->getSize();
        if ($tamano <= 0 || $tamano > self::MAX_BYTES) {
            throw new CorreoRechazado("El archivo es demasiado grande (máximo 10 MB).");
        }
        $total = array_sum(array_map(static fn(CorreoAdjunto $a) => $a->getTamano(), $borrador->getAdjuntos()->toArray()));
        if ($total + $tamano > self::MAX_TOTAL) {
            throw new CorreoRechazado("El correo no puede llevar más de 25 MB de adjuntos.");
        }
        $nombre = self::nombreSeguro($archivo->getClientOriginalName());
        $tipo = self::tipo($archivo->getPathname(), $archivo->getClientMimeType());
        $ruta = $this->ruta($nombre);
        $destino = $this->preparar($ruta);
        $archivo->move(dirname($destino), basename($destino));

        $adjunto = new CorreoAdjunto($borrador, $nombre, $tipo, $tamano, $ruta);
        $this->em->persist($adjunto);

        return $adjunto;
    }

    /** Un archivo que llegó en un correo de fuera (sin flush). */
    public function guardar(CorreoMensaje $mensaje, string $contenido, string $nombre, string $tipo, ?string $contentId, bool $enLinea): ?CorreoAdjunto
    {
        $tamano = strlen($contenido);
        if ($tamano === 0 || $tamano > 3 * self::MAX_BYTES) {
            return null;
        }
        $nombre = self::nombreSeguro($nombre);
        $ruta = $this->ruta($nombre);
        file_put_contents($this->preparar($ruta), $contenido);
        $adjunto = new CorreoAdjunto($mensaje, $nombre, preg_match('~^[a-z0-9.+-]+/[a-z0-9.+-]+$~', $tipo) ? $tipo : "application/octet-stream", $tamano, $ruta, $contentId, $enLinea);
        $this->em->persist($adjunto);

        return $adjunto;
    }

    public function contenido(CorreoAdjunto $adjunto): string
    {
        return (string) @file_get_contents($this->absoluta($adjunto->getRuta()));
    }

    /**
     * Borra del disco los archivos que ya nadie usa. Se llama después de
     * quitar las filas (y del flush).
     *
     * @param list<string> $rutas
     */
    public function purgar(array $rutas): void
    {
        $rutas = array_values(array_unique($rutas));
        if ($rutas === []) {
            return;
        }
        $enUso = array_column($this->em->createQueryBuilder()->select("a.ruta")->from(CorreoAdjunto::class, "a")->where("a.ruta IN (:r)")->setParameter("r", $rutas)->getQuery()->getScalarResult(), "ruta");
        foreach (array_diff($rutas, $enUso) as $ruta) {
            @unlink($this->absoluta($ruta));
        }
    }

    /** @return array<string, mixed> */
    public function presentar(CorreoAdjunto $a, ?int $ahora = null): array
    {
        return [
            "id" => $a->getId(),
            "nombre" => $a->getNombre(),
            "tipo" => $a->getTipo(),
            "tamano" => $a->getTamano(),
            "imagen" => $a->esImagen(),
            "url" => $this->url($a, $ahora),
        ];
    }

    /** Relativa a la API (`/correo/adjuntos/{id}/{firma}?exp=`). */
    public function url(CorreoAdjunto $a, ?int $ahora = null): string
    {
        $exp = (intdiv($ahora ?? time(), self::VENTANA) + self::VIGENCIA_VENTANAS) * self::VENTANA;

        return sprintf("/correo/adjuntos/%d/%s?exp=%d", $a->getId(), $this->firma((int) $a->getId(), $exp), $exp);
    }

    public function firmaValida(int $id, string $firma, int $exp, ?int $ahora = null): bool
    {
        return $exp >= ($ahora ?? time()) && hash_equals($this->firma($id, $exp), $firma);
    }

    public function absoluta(string $ruta): string
    {
        if (str_contains($ruta, "..")) {
            throw new CorreoRechazado("Ruta inválida.", 400);
        }

        return rtrim($this->directorio, "/") . "/correo/" . $ruta;
    }

    /** Se muestra en el navegador (imágenes y PDF); lo demás se descarga. */
    public static function enLineaSegura(string $tipo): bool
    {
        return in_array($tipo, ["image/jpeg", "image/png", "image/gif", "image/webp", "application/pdf"], true);
    }

    public static function nombreSeguro(string $nombre): string
    {
        $limpio = preg_replace('/[\x00-\x1f\x7f\/\\\\:*?"<>|]+/u', "_", trim($nombre)) ?? "";
        $limpio = trim($limpio, ". ");

        return mb_substr($limpio !== "" ? $limpio : "archivo", -160);
    }

    /** El tipo: si el contenido lo dice con claridad, ese; si no, el que declaró el navegador. */
    private static function tipo(string $archivo, ?string $declarado): string
    {
        $info = function_exists("finfo_open") ? @finfo_open(FILEINFO_MIME_TYPE) : false;
        $detectado = $info ? (string) finfo_file($info, $archivo) : "";
        if ($detectado !== "" && $detectado !== "application/octet-stream" && $detectado !== "text/plain") {
            return $detectado;
        }
        $declarado = strtolower(trim((string) $declarado));

        return preg_match('~^[a-z0-9.+-]+/[a-z0-9.+-]+$~', $declarado) ? $declarado : ($detectado ?: "application/octet-stream");
    }

    private function ruta(string $nombre): string
    {
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

        return sprintf("%s/%s%s", date("Y/m"), bin2hex(random_bytes(16)), preg_match('/^[a-z0-9]{1,8}$/', $extension) ? "." . $extension : "");
    }

    private function preparar(string $ruta): string
    {
        $destino = $this->absoluta($ruta);
        if (!is_dir(dirname($destino)) && !mkdir(dirname($destino), 0750, true) && !is_dir(dirname($destino))) {
            throw new CorreoRechazado("No se pudo guardar el archivo.", 500);
        }

        return $destino;
    }

    private function firma(int $id, int $exp): string
    {
        return substr(hash_hmac("sha256", "correo-adjunto|{$id}|{$exp}", $this->secreto), 0, 32);
    }
}
