<?php

declare(strict_types=1);

namespace App\Command;

use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Pago\Cybersource\GestionCybersource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Muestra o cambia `ConfiguracionCybersource` (ADR-047), con la validación de
 * `GestionCybersource` (la misma de la administración). Sin opciones, la muestra.
 *
 *   app:pago:configuracion --pasarela=cybersource --url=https://api.cybersource.com
 *   app:pago:configuracion --org-id=XXXX   |   --sin-org-id
 */
#[AsCommand(name: "app:pago:configuracion", description: "Muestra o cambia la configuración de la pasarela de pago (Cybersource)")]
final class PagoConfiguracionCommand
{
    public function __construct(
        private readonly GestionCybersource $gestion,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "cybersource (real) o simulada")] ?string $pasarela = null,
        #[Option(description: "URL de la API de Cybersource")] ?string $url = null,
        #[Option(description: "Org ID de ThreatMetrix (huella del dispositivo)")] ?string $orgId = null,
        #[Option(description: "Quitar el Org ID (sin huella del dispositivo)")] bool $sinOrgId = false,
    ): int {
        $datos = [];
        if ($pasarela !== null) {
            $valor = strtolower(trim($pasarela));
            if (!in_array($valor, ["cybersource", "simulada"], true)) {
                $io->error("--pasarela debe ser cybersource o simulada.");

                return 1;
            }
            $datos["activo"] = $valor === "cybersource";
        }
        if ($url !== null) {
            $datos["url"] = $url;
        }
        if ($sinOrgId) {
            $datos["orgId"] = "";
        } elseif ($orgId !== null) {
            $datos["orgId"] = $orgId;
        }

        if ($datos !== []) {
            try {
                $this->gestion->guardar($datos, null);
            } catch (VentaRechazada $e) {
                $io->error($e->getMessage());

                return 1;
            }
            $io->success("Configuración de Cybersource guardada.");
        }

        $c = $this->gestion->datos();
        $io->definitionList(
            ["Pasarela" => $c["activo"] ? "cybersource" : "simulada"],
            ["URL" => $c["url"]],
            ["Org ID" => $c["orgId"] ?? "— (sin huella del dispositivo)"],
            ["Actualizada" => $c["actualizadaEn"] !== null ? str_replace("T", " ", substr($c["actualizadaEn"], 0, 16)) . ($c["actualizadaPor"] ? " por {$c["actualizadaPor"]}" : "") : "—"],
        );

        return 0;
    }
}
