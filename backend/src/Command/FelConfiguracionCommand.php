<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ConfiguracionForcon;
use App\Venta\Facturacion\CredencialesFel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Muestra o cambia `ConfiguracionForcon` (ADR-047). Sin opciones, la muestra.
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
        private readonly EntityManagerInterface $em,
        private readonly CredencialesFel $credenciales,
        private readonly ClockInterface $reloj,
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
        $config = $this->em->find(ConfiguracionForcon::class, ConfiguracionForcon::ID);
        if ($config === null) {
            $config = new ConfiguracionForcon();
            $this->em->persist($config);
        }
        $cambios = false;

        if ($certificador !== null) {
            $valor = strtolower(trim($certificador));
            if (!in_array($valor, ["forcon", "simulado"], true)) {
                $io->error("--certificador debe ser forcon o simulado.");

                return 1;
            }
            $config->setActivo($valor === "forcon");
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
        if ($sinUsuario) {
            $config->setCredencialGlobal(null, null);
            $cambios = true;
        } elseif ($usuario !== null) {
            $clave = getenv("FEL_CLAVE") ?: (string) $io->askHidden("Clave del certificador");
            if (trim($usuario) === "" || $clave === "") {
                $io->error("El usuario y la clave no pueden ser vacíos.");

                return 1;
            }
            $config->setCredencialGlobal(trim($usuario), $this->credenciales->cifrar($clave));
            $cambios = true;
        }
        if ($sinEmisoresEnLinea) {
            $config->setEmisoresEnLinea([]);
            $cambios = true;
        } elseif ($emisorEnLinea !== []) {
            $mapa = [];
            foreach ($emisorEnLinea as $par) {
                $partes = array_map("trim", explode(":", $par));
                if (count($partes) !== 2 || $partes[0] === "" || $partes[1] === "") {
                    $io->error("--emisor-en-linea va como NIT_EMPRESA:NIT_EMISOR ({$par}).");

                    return 1;
                }
                $mapa[$partes[0]] = $partes[1];
            }
            $config->setEmisoresEnLinea($mapa);
            $cambios = true;
        }

        if ($cambios) {
            $config->actualizada(null, $this->reloj->now());
            $this->em->flush();
            $io->success("Configuración de Forcon guardada.");
        }

        $emisores = $config->getEmisoresEnLinea();
        $io->definitionList(
            ["Certificador" => $config->isActivo() ? "forcon" : "simulado"],
            ["URL" => $config->getUrl()],
            ["Usuario global" => $config->getUsuario() !== null ? $config->getUsuario() . " (con clave)" : "—"],
            ["Emisores en línea" => $emisores === []
                ? "—"
                : implode(", ", array_map(static fn($e, $n) => "{$e} → {$n}", array_keys($emisores), $emisores))],
            ["Actualizada" => $config->getActualizadaEn()?->format("Y-m-d H:i") ?? "—"],
        );

        return 0;
    }
}
