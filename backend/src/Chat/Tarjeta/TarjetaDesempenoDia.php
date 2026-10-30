<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use App\Acceso\Ubicacion;
use App\Chat\Predeterminado\Contenidos;
use App\Entity\Empresa;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Desempeño del día de una empresa (ADR-045), en la tarjeta del mensaje
 * predeterminado: la misma cifra que el texto, calculada al leer. El id
 * codifica el día y la empresa (0 = todas las empresas): `AAAAMMDD` ·
 * 1 000 000 + empresa. Permiso `reporte.ventas` en la empresa del día.
 */
final class TarjetaDesempenoDia implements Tarjeta
{
    public const TIPO = "DesempenoDia";

    private const MULTIPLO = 1_000_000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $auth,
        private readonly Contenidos $contenidos,
    ) {}

    /** El id de la tarjeta de un día y una empresa (null = todas). */
    public static function codigo(\DateTimeImmutable $dia, ?int $empresa): int
    {
        return (int) $dia->format("Ymd") * self::MULTIPLO + ($empresa ?? 0);
    }

    public function tipo(): string
    {
        return self::TIPO;
    }

    public function puedeVer(): bool
    {
        return $this->auth->isGranted("reporte.ventas");
    }

    public function resolver(array $ids): array
    {
        $datos = [];
        foreach ($ids as $id) {
            $dia = \DateTimeImmutable::createFromFormat("!Ymd", (string) intdiv($id, self::MULTIPLO));
            $empresaId = $id % self::MULTIPLO ?: null;
            $empresa = $empresaId === null ? null : $this->em->find(Empresa::class, $empresaId);
            if ($dia === false || ($empresaId !== null && $empresa === null)) {
                continue;
            }
            if (!$this->auth->isGranted("reporte.ventas", Ubicacion::empresa($empresaId))) {
                continue;
            }
            $datos[$id] = $this->contenidos->desempeno($dia, $empresa);
        }

        return $datos;
    }
}
