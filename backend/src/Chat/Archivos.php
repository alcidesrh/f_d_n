<?php

declare(strict_types=1);

namespace App\Chat;

use App\Entity\ChatArchivo;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Archivos del chat en disco (`CHAT_ARCHIVOS_DIR`). El tipo se decide por el
 * contenido, no por lo que diga el navegador: imágenes (las reconoce
 * `getimagesize`), PDF, Word/Excel (zip) y texto/CSV. Se sirven por una URL
 * firmada que vence: así una etiqueta `<img>` los muestra sin el Bearer,
 * y un enlace copiado deja de servir en uno o dos días.
 */
final class Archivos
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    /** Ventana de las firmas: misma URL durante 6 h (el navegador la cachea). */
    private const VENTANA = 6 * 3600;
    private const VIGENCIA_VENTANAS = 5;

    private const IMAGENES = [IMAGETYPE_JPEG => "image/jpeg", IMAGETYPE_PNG => "image/png", IMAGETYPE_GIF => "image/gif", IMAGETYPE_WEBP => "image/webp"];
    private const ZIP = ["docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"];
    private const TEXTO = ["txt" => "text/plain", "csv" => "text/csv"];

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(env: "CHAT_ARCHIVOS_DIR")] private readonly string $directorio,
        #[Autowire("%kernel.secret%")] private readonly string $secreto,
    ) {}

    public function subir(UploadedFile $archivo, Usuario $autor): ChatArchivo
    {
        if (!$archivo->isValid()) {
            throw new ChatRechazado($archivo->getError() === UPLOAD_ERR_INI_SIZE || $archivo->getError() === UPLOAD_ERR_FORM_SIZE
                ? "El archivo es demasiado grande (máximo 10 MB)."
                : "No se pudo recibir el archivo.");
        }
        $tamano = (int) $archivo->getSize();
        if ($tamano <= 0 || $tamano > self::MAX_BYTES) {
            throw new ChatRechazado("El archivo es demasiado grande (máximo 10 MB).");
        }
        $origen = $archivo->getPathname();
        $nombre = self::nombreSeguro($archivo->getClientOriginalName());
        [$tipo, $extension, $ancho, $alto] = self::reconocer($origen, strtolower(pathinfo($nombre, PATHINFO_EXTENSION)))
            ?? throw new ChatRechazado("Tipo de archivo no permitido: se aceptan fotos, PDF, Word, Excel y texto.");

        $ruta = sprintf("%s/%s.%s", date("Y/m"), bin2hex(random_bytes(16)), $extension);
        $destino = $this->absoluta($ruta);
        if (!is_dir(dirname($destino)) && !mkdir(dirname($destino), 0750, true) && !is_dir(dirname($destino))) {
            throw new ChatRechazado("No se pudo guardar el archivo.", 500);
        }
        $archivo->move(dirname($destino), basename($destino));

        $registro = new ChatArchivo($autor, $nombre, $tipo, $tamano, $ruta, $ancho, $alto);
        $this->em->persist($registro);
        $this->em->flush();

        return $registro;
    }

    /**
     * Los archivos subidos por `$autor` que aún no están en un mensaje.
     *
     * @param list<int> $ids
     *
     * @return list<ChatArchivo>
     */
    public function sueltosDe(array $ids, Usuario $autor): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $archivos = $this->em->getRepository(ChatArchivo::class)->findBy(["id" => $ids, "autor" => $autor, "mensaje" => null]);
        if (count($archivos) !== count($ids)) {
            throw new ChatRechazado("Algún archivo no existe o ya se envió.");
        }

        return $archivos;
    }

    /** @return array<string, mixed> */
    public function presentar(ChatArchivo $a, ?int $ahora = null): array
    {
        $exp = (intdiv($ahora ?? time(), self::VENTANA) + self::VIGENCIA_VENTANAS) * self::VENTANA;

        return [
            "id" => $a->getId(),
            "nombre" => $a->getNombre(),
            "tipo" => $a->getTipo(),
            "tamano" => $a->getTamano(),
            "ancho" => $a->getAncho(),
            "alto" => $a->getAlto(),
            "imagen" => $a->esImagen(),
            "url" => sprintf("/chat/archivos/%d/%s?exp=%d", $a->getId(), $this->firma((int) $a->getId(), $exp), $exp),
        ];
    }

    public function firmaValida(int $id, string $firma, int $exp, ?int $ahora = null): bool
    {
        return $exp >= ($ahora ?? time()) && hash_equals($this->firma($id, $exp), $firma);
    }

    public function absoluta(string $ruta): string
    {
        return rtrim($this->directorio, "/") . "/" . $ruta;
    }

    /** Borra los subidos que nunca se enviaron; devuelve cuántos. */
    public function purgarSueltos(\DateTimeImmutable $antesDe): int
    {
        /** @var list<ChatArchivo> $sueltos */
        $sueltos = $this->em->createQuery("SELECT a FROM App\Entity\ChatArchivo a WHERE a.mensaje IS NULL AND a.creadoEn < :antes")
            ->setParameter("antes", $antesDe)->getResult();
        foreach ($sueltos as $a) {
            @unlink($this->absoluta($a->getRuta()));
            $this->em->remove($a);
        }
        $this->em->flush();

        return count($sueltos);
    }

    private function firma(int $id, int $exp): string
    {
        return substr(hash_hmac("sha256", "chat-archivo:$id:$exp", $this->secreto), 0, 32);
    }

    /**
     * Tipo según el contenido: `[mime, extensión, ancho, alto]` o null.
     *
     * @return array{string, string, ?int, ?int}|null
     */
    public static function reconocer(string $archivo, string $extensionCliente): ?array
    {
        $imagen = @getimagesize($archivo);
        if ($imagen !== false && isset(self::IMAGENES[$imagen[2]])) {
            $mime = self::IMAGENES[$imagen[2]];

            return [$mime, substr($mime, 6) === "jpeg" ? "jpg" : substr($mime, 6), $imagen[0], $imagen[1]];
        }
        $cabeza = (string) file_get_contents($archivo, false, null, 0, 8192);
        if (str_starts_with($cabeza, "%PDF-")) {
            return ["application/pdf", "pdf", null, null];
        }
        if (str_starts_with($cabeza, "PK\x03\x04") && isset(self::ZIP[$extensionCliente])) {
            return [self::ZIP[$extensionCliente], $extensionCliente, null, null];
        }
        if (isset(self::TEXTO[$extensionCliente]) && !str_contains($cabeza, "\0") && mb_check_encoding($cabeza, "UTF-8")) {
            return [self::TEXTO[$extensionCliente], $extensionCliente, null, null];
        }

        return null;
    }

    /** Sin rutas, controles ni caracteres raros; máximo 160. */
    public static function nombreSeguro(string $nombre): string
    {
        $nombre = basename(str_replace("\\", "/", $nombre));
        $nombre = preg_replace('/[\x00-\x1F\x7F"<>:|?*]/u', "", $nombre) ?? "";
        $nombre = trim($nombre) !== "" ? trim($nombre) : "archivo";

        return mb_strlen($nombre) > 160 ? mb_substr($nombre, -160) : $nombre;
    }
}
