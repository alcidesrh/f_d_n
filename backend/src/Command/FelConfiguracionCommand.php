<?php

declare(strict_types=1);

namespace App\Command;

use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\Forcon\GestionForcon;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Muestra o cambia `ConfiguracionForcon` (ADR-047), con la validación de
 * `GestionForcon` (la misma de la administración). Sin opciones, la muestra.
 * La clave global se pide oculta (o `FEL_CLAVE` en el entorno, para scripts).
 *
 *   app:fel:configuracion --certificador=forcon --url=https://...
 *   app:fel:configuracion --emisor-en-linea=2033683-7:6907303-1 --emisor-en-linea=...
 *   app:fel:configuracion --usuario=USR   (pide la clave)   |   --sin-usuario
 */
#[AsCommand(name: "app:fel:configuracion", description: "Muestra o cambia la configuración del certificador FEL (Forcon)")]
final class FelConfiguracionCommand
{
    public function __construct(
        private readonly GestionForcon $gestion,
    ) {}

    /**
     * @param list<string> $emisorEnLinea
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "forcon (real) o simulado")] ?string $certificador = null,
        #[Option(description: "URL de la API de Forcon")] ?string $url = null,
        #[Option(description: "Usuario global (si una empresa no tiene credenciales); pide la clave")] ?string $usuario = null,
        #[Option(description: "Quitar el usuario y la clave globales")] bool $sinUsuario = false,
        #[Option(description: "NIT_EMPRESA:NIT_EMISOR; reemplaza la lista (repetible)")] array $emisorEnLinea = [],
        #[Option(description: "Vaciar los emisores en línea")] bool $sinEmisoresEnLinea = false,
    ): int {
        $datos = [];
        if ($certificador !== null) {
            $valor = strtolower(trim($certificador));
            if (!in_array($valor, ["forcon", "simulado"], true)) {
                $io->error("--certificador debe ser forcon o simulado.");

                return 1;
            }
            $datos["activo"] = $valor === "forcon";
        }
        if ($url !== null) {
            $datos["url"] = $url;
        }
        if ($sinUsuario) {
            $datos["usuario"] = "";
        } elseif ($usuario !== null) {
            $datos["usuario"] = $usuario;
            $datos["clave"] = getenv("FEL_CLAVE") ?: (string) $io->askHidden("Clave del certificador (vacía: conservar la guardada)");
        }
        if ($sinEmisoresEnLinea) {
            $datos["emisoresEnLinea"] = [];
        } elseif ($emisorEnLinea !== []) {
            $datos["emisoresEnLinea"] = array_map(static function (string $par): array {
                [$empresa, $emisor] = array_map("trim", explode(":", $par, 2)) + [1 => ""];

                return ["empresa" => $empresa, "emisor" => $emisor];
            }, $emisorEnLinea);
        }

        if ($datos !== []) {
            try {
                $this->gestion->guardar($datos, null);
            } catch (VentaRechazada $e) {
                $io->error($e->getMessage());

                return 1;
            }
            $io->success("Configuración de Forcon guardada.");
        }

        $c = $this->gestion->datos();
        $io->definitionList(
            ["Certificador" => $c["activo"] ? "forcon" : "simulado"],
            ["URL" => $c["url"]],
            ["Usuario global" => $c["usuario"] !== null ? $c["usuario"] . ($c["conClave"] ? " (con clave)" : "") : "—"],
            ["Emisores en línea" => $c["emisoresEnLinea"] === []
                ? "—"
                : implode(", ", array_map(static fn(array $e) => "{$e["empresa"]} → {$e["emisor"]}", $c["emisoresEnLinea"]))],
            ["Actualizada" => $c["actualizadaEn"] !== null ? str_replace("T", " ", substr($c["actualizadaEn"], 0, 16)) . ($c["actualizadaPor"] ? " por {$c["actualizadaPor"]}" : "") : "—"],
        );

        return 0;
    }
}
