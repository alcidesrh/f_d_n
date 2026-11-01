<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ConfiguracionCybersource;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Muestra o cambia `ConfiguracionCybersource` (ADR-047). Sin opciones, la muestra.
 *
 *   app:pago:configuracion --pasarela=cybersource --url=https://api.cybersource.com
 *   app:pago:configuracion --org-id=XXXX   |   --sin-org-id
 */
#[AsCommand(name: "app:pago:configuracion", description: "Muestra o cambia la configuración de la pasarela de pago (Cybersource)")]
final class PagoConfiguracionCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $reloj,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "cybersource (real) o simulada")] ?string $pasarela = null,
        #[Option(description: "URL de la API de Cybersource")] ?string $url = null,
        #[Option(description: "Org ID de ThreatMetrix (huella del dispositivo)")] ?string $orgId = null,
        #[Option(description: "Quitar el Org ID (sin huella del dispositivo)")] bool $sinOrgId = false,
    ): int {
        $config = $this->em->find(ConfiguracionCybersource::class, ConfiguracionCybersource::ID);
        if ($config === null) {
            $config = new ConfiguracionCybersource();
            $this->em->persist($config);
        }
        $cambios = false;

        if ($pasarela !== null) {
            $valor = strtolower(trim($pasarela));
            if (!in_array($valor, ["cybersource", "simulada"], true)) {
                $io->error("--pasarela debe ser cybersource o simulada.");

                return 1;
            }
            $config->setActivo($valor === "cybersource");
            $cambios = true;
        }
        if ($url !== null) {
            if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, "https://")) {
                $io->error("--url debe ser una URL https.");

                return 1;
            }
            $config->setUrl(rtrim($url, "/"));
            $cambios = true;
        }
        if ($sinOrgId) {
            $config->setOrgId(null);
            $cambios = true;
        } elseif ($orgId !== null) {
            $config->setOrgId($orgId);
            $cambios = true;
        }

        if ($cambios) {
            $config->actualizada(null, $this->reloj->now());
            $this->em->flush();
            $io->success("Configuración de Cybersource guardada.");
        }

        $io->definitionList(
            ["Pasarela" => $config->isActivo() ? "cybersource" : "simulada"],
            ["URL" => $config->getUrl()],
            ["Org ID" => $config->getOrgId() ?? "— (sin huella del dispositivo)"],
            ["Actualizada" => $config->getActualizadaEn()?->format("Y-m-d H:i") ?? "—"],
        );

        return 0;
    }
}
