<?php

declare(strict_types=1);

namespace App\Metadata;

use App\Entity\Usuario;

/**
 * Cuándo se publica una revisión de la metadata del cliente (ADR-036):
 *
 * - **Schema GraphQL**: siempre solo. Lo revisa el despliegue
 *   (`revisarDespliegue`) y, por si no corrió, la primera lectura del
 *   manifiesto con código nuevo (`vigente`).
 * - **Configuración de entidades**: en modo automático, al terminar la
 *   petición o el comando que la guardó (`configuracionGuardada`); en modo
 *   manual espera a que un administrador publique (`publicar`).
 */
final class PublicacionMetadata
{
    public const NOTA_MAXIMA = 500;

    public function __construct(
        private readonly RegistroRevisiones $registro,
        private readonly HuellasVigentes $huellas,
        private readonly ConfiguracionesTocadas $tocadas,
    ) {}

    public function modo(): ModoPublicacion
    {
        return $this->registro->modo;
    }

    /**
     * La revisión que leen los navegadores. La primera vez toma todo lo
     * vigente como punto de partida; si el schema cambió desde la última (se
     * desplegó código sin `app:metadata:revisar`), antes publica el schema.
     */
    public function vigente(): Estado
    {
        $estado = $this->registro->vigente();
        if ($estado === null) {
            return $this->registro->registrar(Origen::DESPLIEGUE, $this->huellas->esquema(), $this->huellas->configuraciones(), true)[0]
                ?? $this->registro->vigente()
                ?? Estado::inicial();
        }
        $esquema = $this->huellas->esquema();
        if ($esquema === $estado->esquemaHuella) {
            return $estado;
        }

        // Otro proceso pudo publicarlo un instante antes: entonces no hay cambios y se relee.
        return $this->registro->registrar(Origen::DESPLIEGUE, $esquema, null)[0] ?? $this->registro->vigente() ?? $estado;
    }

    /** Lo que falta publicar: lo vigente contra lo publicado. */
    public function pendiente(): Cambios
    {
        return $this->vigente()->cambios($this->huellas->esquema(), $this->huellas->configuraciones(), true);
    }

    /**
     * Publica a mano todo lo vigente. Con `$aviso` cada navegador ofrece
     * «Recargar» con la nota; con `$completa` cada uno vuelve a pedir todo
     * (schema, configuración y listas guardadas) aunque no se vean cambios.
     *
     * @throws PublicacionRechazada sin nada que publicar o con una nota muy larga
     */
    public function publicar(Usuario $usuario, ?string $nota, bool $aviso, bool $completa): Estado
    {
        $nota = self::nota($nota);
        $nueva = $this->registro->registrar(Origen::MANUAL, $this->huellas->esquema(), $this->huellas->configuraciones(), true, $usuario, $nota, $aviso, $completa);
        if ($nueva === null) {
            throw new PublicacionRechazada("No hay cambios que publicar. Marca «Recargar todo» para que cada navegador vuelva a pedir la configuración de todos modos.", "sin_cambios", 409);
        }

        return $nueva[0];
    }

    /**
     * Al desplegar: el schema y, en modo automático (o la primera vez), toda la
     * configuración. Lo que anotó una sincronización previa en el mismo
     * proceso ya entra aquí.
     *
     * @return array{Estado, Cambios}|null
     */
    public function revisarDespliegue(): ?array
    {
        $this->tocadas->reset();
        $configs = $this->registro->modo === ModoPublicacion::AUTOMATICA || $this->registro->vigente() === null
            ? $this->huellas->configuraciones()
            : null;

        return $this->registro->registrar(Origen::DESPLIEGUE, $this->huellas->esquema(true), $configs, $configs !== null);
    }

    /**
     * Fin de una petición o comando que guardó configuración: en modo
     * automático publica la de esas entidades (null: todas).
     *
     * @param list<string>|null $entidades
     * @param Usuario|null $usuario quien la guardó (null desde la consola)
     *
     * @return array{Estado, Cambios}|null
     */
    public function configuracionGuardada(?array $entidades, ?Usuario $usuario = null): ?array
    {
        if ($this->registro->modo !== ModoPublicacion::AUTOMATICA) {
            return null;
        }
        if ($this->registro->vigente() === null) {
            // Sin punto de partida, lo que se guardó entra en él.
            $this->vigente();

            return null;
        }

        return $this->registro->registrar(Origen::AUTOMATICA, null, $this->huellas->configuraciones($entidades), $entidades === null, $usuario);
    }

    /**
     * Lo que muestra `/configuracion/publicacion`: el modo, lo que falta
     * publicar y las últimas revisiones.
     *
     * @return array<string, mixed>
     */
    public function resumen(): array
    {
        $estado = $this->vigente();

        return [
            "modo" => $this->registro->modo->value,
            "revision" => $estado->revision,
            "pendiente" => $estado->cambios($this->huellas->esquema(), $this->huellas->configuraciones(), true)->toArray(),
            "historial" => $this->registro->historial(),
        ];
    }

    /** Sin espacios de más; vacía es ninguna. */
    public static function nota(?string $nota): ?string
    {
        $nota = trim((string) $nota);
        if ($nota === "") {
            return null;
        }
        if (mb_strlen($nota) > self::NOTA_MAXIMA) {
            throw new PublicacionRechazada(sprintf("La nota no puede pasar de %d caracteres.", self::NOTA_MAXIMA), "nota_larga");
        }

        return $nota;
    }
}
