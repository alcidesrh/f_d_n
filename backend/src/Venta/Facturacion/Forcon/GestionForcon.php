<?php

declare(strict_types=1);

namespace App\Venta\Facturacion\Forcon;

use App\Entity\ConfiguracionForcon;
use App\Entity\CredencialFel;
use App\Entity\Empresa;
use App\Entity\Usuario;
use App\Venta\CifradoCredenciales;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\CredencialesFel;
use App\Venta\Facturacion\EmisorEnLinea;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Ver, cambiar y probar `ConfiguracionForcon` (ADR-047): lo usan la
 * administración (`ConfiguracionProveedoresController`) y
 * `app:fel:configuracion`, con la misma validación. La clave nunca sale:
 * solo si hay una.
 */
final class GestionForcon
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AjustesForcon $ajustes,
        private readonly CifradoCredenciales $cifrado,
        private readonly ClockInterface $reloj,
        private readonly ClienteForcon $cliente,
    ) {}

    /** @return array<string, mixed> */
    public function datos(): array
    {
        $c = $this->ajustes->actual();
        $conCredencial = [];
        foreach ($this->em->getRepository(CredencialFel::class)->findAll() as $credencial) {
            // Ilegible: cifrada con otro APP_SECRET (hay que volver a cargarla).
            $conCredencial[spl_object_id($credencial->getEmpresa())] = $this->cifrado->descifrar(CredencialesFel::CONTEXTO, $credencial->getClaveCifrada()) !== null ? "propia" : "ilegible";
        }

        return [
            "activo" => $c->isActivo(),
            "url" => $c->getUrl(),
            "usuario" => $c->getUsuario(),
            "conClave" => $c->getClaveCifrada() !== null,
            "emisoresEnLinea" => array_map(
                static fn($empresa, $emisor) => ["empresa" => (string) $empresa, "emisor" => (string) $emisor],
                array_keys($c->getEmisoresEnLinea()),
                array_values($c->getEmisoresEnLinea()),
            ),
            "actualizadaEn" => $c->getActualizadaEn()?->format(\DATE_ATOM),
            "actualizadaPor" => $c->getActualizadaPor()?->getLabel(),
            "empresas" => array_map(
                static fn(Empresa $e) => [
                    "nombre" => (string) ($e->getNombreCorto() ?: $e->getNombre()),
                    "nit" => (string) $e->getNit(),
                    "credencial" => $conCredencial[spl_object_id($e)] ?? null,
                ],
                $this->empresasConNit(),
            ),
        ];
    }

    /**
     * Cambia solo lo que viene: `activo`, `url`, `usuario` (vacío quita la
     * credencial global) con `clave` (vacía conserva la guardada) y
     * `emisoresEnLinea` (`[{ empresa, emisor }]`, NIT de cada una).
     *
     * @param array<string, mixed> $datos
     *
     * @throws VentaRechazada si algo no es válido (no guarda nada)
     */
    public function guardar(array $datos, ?Usuario $por): ConfiguracionForcon
    {
        $config = $this->em->find(ConfiguracionForcon::class, ConfiguracionForcon::ID);
        if ($config === null) {
            $config = new ConfiguracionForcon();
            $this->em->persist($config);
        }

        if (array_key_exists("activo", $datos)) {
            $config->setActivo((bool) $datos["activo"]);
        }
        if (array_key_exists("url", $datos)) {
            $config->setUrl(self::url($datos["url"]));
        }
        if (array_key_exists("usuario", $datos)) {
            $usuario = trim((string) $datos["usuario"]);
            $clave = (string) ($datos["clave"] ?? "");
            if ($usuario === "") {
                $config->setCredencialGlobal(null, null);
            } elseif (mb_strlen($usuario) > 100) {
                throw new VentaRechazada("El usuario del certificador es demasiado largo.", "usuario_invalido");
            } elseif ($clave !== "") {
                $config->setCredencialGlobal($usuario, $this->cifrado->cifrar(CredencialesFel::CONTEXTO, $clave));
            } elseif ($config->getClaveCifrada() !== null) {
                $config->setCredencialGlobal($usuario, $config->getClaveCifrada());
            } else {
                throw new VentaRechazada("Escribí la clave del usuario global.", "clave_requerida");
            }
        }
        if (array_key_exists("emisoresEnLinea", $datos)) {
            $config->setEmisoresEnLinea($this->emisores($datos["emisoresEnLinea"]));
        }

        $config->actualizada($por, $this->reloj->now());
        $this->em->flush();

        return $config;
    }

    /**
     * Consulta en Forcon los datos de un establecimiento, sin caché, con la
     * URL y las credenciales guardadas (funciona aunque Forcon esté apagado).
     *
     * @return array<string, mixed>
     *
     * @throws VentaRechazada si Forcon no responde o rechaza
     */
    public function probar(string $nit, int $establecimiento): array
    {
        if (trim($nit) === "" || $establecimiento < 1) {
            throw new VentaRechazada("Elegí la empresa y el número de establecimiento.", "prueba_invalida");
        }
        try {
            $r = $this->cliente->get(ClienteForcon::DATOS_EMISOR, ["NIT" => DteJsonForcon::nit($nit), "Establecimiento" => $establecimiento], $nit);
        } catch (CertificacionFallida $e) {
            throw new VentaRechazada($e->getMessage(), "prueba_fallida");
        }
        if (($r["Resultado"] ?? false) !== true) {
            throw new VentaRechazada(
                sprintf("Forcon respondió, pero sin datos del establecimiento %d: %s", $establecimiento, (string) ($r["Descripcion"] ?? "sin detalle")),
                "prueba_fallida",
            );
        }

        return DatosEmisorForcon::desdeRespuesta($r);
    }

    /** @throws VentaRechazada */
    public static function url(mixed $url): string
    {
        $url = rtrim(trim((string) $url), "/");
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, "https://") || mb_strlen($url) > 255) {
            throw new VentaRechazada("La URL debe ser una dirección https válida.", "url_invalida");
        }

        return $url;
    }

    /**
     * @return array<string, string> NIT de la empresa => NIT del emisor, tal como los tienen las empresas
     *
     * @throws VentaRechazada
     */
    private function emisores(mixed $lista): array
    {
        if (!is_array($lista)) {
            throw new VentaRechazada("Los emisores en línea no son válidos.", "emisores_invalidos");
        }
        $porNit = [];
        foreach ($this->empresasConNit() as $e) {
            $porNit[EmisorEnLinea::normalizar((string) $e->getNit())] = $e;
        }
        $mapa = [];
        foreach ($lista as $par) {
            $empresa = $porNit[EmisorEnLinea::normalizar((string) ($par["empresa"] ?? ""))] ?? null;
            $emisor = $porNit[EmisorEnLinea::normalizar((string) ($par["emisor"] ?? ""))] ?? null;
            if ($empresa === null || $emisor === null) {
                throw new VentaRechazada("Cada emisor en línea debe ser un par de empresas con NIT.", "emisores_invalidos");
            }
            if ($empresa === $emisor) {
                throw new VentaRechazada(sprintf("%s no puede facturar a nombre de sí misma: quite el renglón.", $empresa->getNombre()), "emisores_invalidos");
            }
            if (isset($mapa[$empresa->getNit()])) {
                throw new VentaRechazada(sprintf("%s aparece dos veces en los emisores en línea.", $empresa->getNombre()), "emisores_invalidos");
            }
            $mapa[(string) $empresa->getNit()] = (string) $emisor->getNit();
        }

        return $mapa;
    }

    /** @return list<Empresa> */
    private function empresasConNit(): array
    {
        return array_values(array_filter(
            $this->em->getRepository(Empresa::class)->findBy([], ["nombre" => "ASC"]),
            static fn(Empresa $e) => (string) $e->getNit() !== "",
        ));
    }
}
