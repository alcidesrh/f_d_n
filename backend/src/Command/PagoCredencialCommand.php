<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Empresa;
use App\Venta\Pago\Cybersource\CredencialesCybersource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Carga el comercio de una empresa en Cybersource (merchant id, key id y
 * llave secreta compartida, del Business Center → Key Management). La llave
 * se pide oculta (o `PAGO_SECRETO` en el entorno, para scripts) y se guarda cifrada.
 */
#[AsCommand(name: "app:pago:credencial", description: "Guarda el comercio de Cybersource de una empresa (por NIT)")]
final class PagoCredencialCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CredencialesCybersource $credenciales,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: "NIT de la empresa (sin guion)")] string $nit,
        #[Argument(description: "Merchant ID de Cybersource")] string $comercio,
        #[Argument(description: "Key ID de la llave compartida")] string $llave,
    ): int {
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy(["nit" => $nit]);
        if ($empresa === null) {
            $io->error("No hay empresa con NIT {$nit}.");

            return 1;
        }
        $secreto = trim(getenv("PAGO_SECRETO") ?: (string) $io->askHidden("Llave secreta compartida (base64)"));
        if ($secreto === "" || base64_decode($secreto, true) === false) {
            $io->error("La llave secreta debe ser el texto base64 que entrega el Business Center.");

            return 1;
        }

        $this->credenciales->guardar($empresa, trim($comercio), trim($llave), $secreto);
        $io->success(sprintf("Comercio %s guardado para %s.", $comercio, $empresa->getNombre()));

        return 0;
    }
}
