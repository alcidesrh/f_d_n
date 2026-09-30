<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Empresa;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\CredencialesFel;
use App\Venta\Facturacion\Forcon\DatosEmisorForcon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Carga las credenciales del certificador FEL (Forcon) de una empresa y,
 * con `--probar`, consulta los datos del establecimiento para verificarlas.
 * La clave se pide oculta (o `FEL_CLAVE` en el entorno, para scripts).
 */
#[AsCommand(name: "app:fel:credencial", description: "Guarda las credenciales del certificador FEL de una empresa (por NIT)")]
final class FelCredencialCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CredencialesFel $credenciales,
        private readonly DatosEmisorForcon $emisores,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: "NIT de la empresa (sin guion)")] string $nit,
        #[Argument(description: "Usuario del certificador")] string $usuario,
        #[Option(description: "Consultar el establecimiento N para probar las credenciales")] ?int $probar = null,
    ): int {
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy(["nit" => $nit]);
        if ($empresa === null) {
            $io->error("No hay empresa con NIT {$nit}.");

            return 1;
        }
        $clave = getenv("FEL_CLAVE") ?: (string) $io->askHidden("Clave del certificador");
        if ($clave === "") {
            $io->error("La clave no puede ser vacía.");

            return 1;
        }

        $this->credenciales->guardar($empresa, $usuario, $clave);
        $io->success(sprintf("Credenciales guardadas para %s.", $empresa->getNombre()));

        if ($probar !== null) {
            try {
                $datos = $this->emisores->de($nit, $probar);
                $io->definitionList(...array_map(static fn($k, $v) => [$k => (string) $v], array_keys($datos), $datos));
            } catch (CertificacionFallida $e) {
                $io->warning("La prueba falló: " . $e->getMessage());

                return 1;
            }
        }

        return 0;
    }
}
