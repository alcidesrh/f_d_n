<?php

declare(strict_types=1);

namespace App\Actividad\Tarea;

use App\Actividad\AjustesBitacora;
use App\Actividad\Particiones;
use App\Actividad\ResumenDiario;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MantenerBitacoraHandler
{
    public function __construct(
        private readonly AjustesBitacora $ajustes,
        private readonly Particiones $particiones,
        private readonly ResumenDiario $resumen,
        private readonly ClockInterface $reloj,
        #[Autowire("%env(BITACORA_EXPORTACION_DIR)%")]
        private readonly string $exportacion,
    ) {}

    /** @return array{particionesCreadas: list<string>, resumido: ?string, usuariosResumidos: int, particionesBorradas: list<string>, resumenesBorrados: int} */
    public function __invoke(MantenerBitacora $mensaje): array
    {
        $config = $this->ajustes->actual();
        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now())->setTimezone(new \DateTimeZone(Particiones::ZONA));

        $creadas = $this->particiones->asegurar($ahora);

        $resumido = null;
        $usuarios = 0;
        $ayer = ResumenDiario::dia($ahora)->modify("-1 day");
        if ($ahora->format("H:i") >= $config->getHoraResumen() && !$this->resumen->hay($ayer)) {
            $usuarios = $this->resumen->guardar($ayer);
            $resumido = $ayer->format("Y-m-d");
        }

        $borradas = $this->particiones->purgar(
            $ahora->modify(sprintf("-%d months", max(1, $config->getRetencionMeses()))),
            $config->isExportarAntesDeBorrar() ? $this->exportacion : null,
        );
        $resumenes = $config->getRetencionResumenMeses() > 0
            ? $this->resumen->purgar($ahora->modify(sprintf("-%d months", $config->getRetencionResumenMeses())))
            : 0;

        return [
            "particionesCreadas" => $creadas,
            "resumido" => $resumido,
            "usuariosResumidos" => $usuarios,
            "particionesBorradas" => $borradas,
            "resumenesBorrados" => $resumenes,
        ];
    }
}
