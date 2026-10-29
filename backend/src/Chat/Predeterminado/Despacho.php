<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado;

use App\Entity\Enum\TipoMensajePredeterminado;
use App\Entity\MensajePredeterminado;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Las corridas de los mensajes predeterminados (ADR-045):
 *
 * - `pendientes()` (cada 5 minutos, `Tarea::MENSAJES_PREDETERMINADOS`): los
 *   de hora cuya hora de hoy ya pasó y las salidas de cada ruta cuya hora
 *   de salida ya pasó.
 * - `anomalias()` (de madrugada, `Tarea::REVISAR_ANOMALIAS`): lo del día
 *   anterior.
 *
 * Cada instancia por su lado: un fallo se anota y no detiene a las demás.
 */
final class Despacho
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Contenidos $contenidos,
        private readonly Difusion $difusion,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return array{envios: int, destinatarios: int, fallidos: int} */
    public function pendientes(\DateTimeImmutable $ahora): array
    {
        $r = ["envios" => 0, "destinatarios" => 0, "fallidos" => 0];
        foreach ($this->activas() as $m) {
            if ($m->getTipo()->esAnomalia()) {
                continue;
            }
            $this->seguro($m, $r, function () use ($m, $ahora): array {
                return array_map(fn(Pieza $p) => $this->difusion->enviar($m, $p), $this->vencidas($m, $ahora));
            });
        }

        return $r;
    }

    /** @return array{envios: int, destinatarios: int, fallidos: int} */
    public function anomalias(\DateTimeImmutable $dia): array
    {
        $r = ["envios" => 0, "destinatarios" => 0, "fallidos" => 0];
        foreach ($this->activas() as $m) {
            if (!$m->getTipo()->esAnomalia()) {
                continue;
            }
            $this->seguro($m, $r, function () use ($m, $dia): array {
                $pieza = $this->contenidos->anomalias($m, $dia);

                return $pieza === null ? [] : [$this->difusion->enviar($m, $pieza)];
            });
        }

        return $r;
    }

    /**
     * Lo que saldría ahora para probar la instancia: el contenido de hoy, la
     * última salida de la ruta cuya hora ya pasó o las anomalías de ayer (sin ellas,
     * un aviso de que no hubo).
     */
    public function muestra(MensajePredeterminado $m, \DateTimeImmutable $ahora): Pieza
    {
        $sin = static fn(string $texto) => new Pieza("prueba", $texto);

        return match ($m->getTipo()) {
            TipoMensajePredeterminado::LIBRE => new Pieza("prueba", ""),
            TipoMensajePredeterminado::VENTAS_DIA => $this->contenidos->ventasDelDia($m, $ahora),
            TipoMensajePredeterminado::DESEMPENO_DIA => $this->contenidos->desempenoDelDia($m, $ahora),
            TipoMensajePredeterminado::SALIDA_INICIADA => (function () use ($m, $ahora, $sin): Pieza {
                $salidas = $this->contenidos->salidasIniciadas($m, $ahora->modify("-30 days"), $ahora);
                $ultima = end($salidas);

                return ($ultima === false ? null : $this->contenidos->salidaIniciada($ultima))
                    ?? $sin("Salida iniciada: en los últimos 30 días no salió ninguna salida de esta ruta. Así llegará cuando pase la hora de la próxima.");
            })(),
            TipoMensajePredeterminado::ANOMALIA_REASIGNACION, TipoMensajePredeterminado::ANOMALIA_TARIFA => $this->contenidos->anomalias($m, $ahora->modify("-1 day"))
                ?? $sin(sprintf("%s: ayer no hubo ninguna. Cuando la revisión de la madrugada encuentre una, llegará así.", $m->getTipo()->nombre())),
        };
    }

    /**
     * ¿Le toca hoy a una instancia de hora? Ya pasó su hora, hoy es uno de
     * sus días y la instancia existía a esa hora (crearla a las 23:00 con
     * hora 22:00 no la manda hasta mañana).
     */
    public static function tocaHoy(MensajePredeterminado $m, \DateTimeImmutable $ahora): bool
    {
        $tipo = $m->getTipo();
        $hora = $m->getHora() ?? ($tipo === TipoMensajePredeterminado::LIBRE ? null : TipoMensajePredeterminado::HORA_CIERRE);
        if (!$tipo->conHora() || $hora === null) {
            return false;
        }
        if ($tipo->conDias() && $m->getDias() !== [] && !in_array((int) $ahora->format("N"), $m->getDias(), true)) {
            return false;
        }
        [$h, $min] = array_map("intval", explode(":", $hora));
        $momento = $ahora->setTime($h, $min);

        return $ahora >= $momento && $m->getCreadoEn() < $momento;
    }

    /** @return list<Pieza> */
    private function vencidas(MensajePredeterminado $m, \DateTimeImmutable $ahora): array
    {
        $tipo = $m->getTipo();
        if ($tipo === TipoMensajePredeterminado::SALIDA_INICIADA) {
            $desde = max($ahora->modify(Contenidos::VENTANA_SALIDA), $m->getCreadoEn());

            return array_values(array_filter(array_map(
                $this->contenidos->salidaIniciada(...),
                $this->contenidos->salidasIniciadas($m, $desde, $ahora, sinAvisar: true),
            )));
        }
        if (!self::tocaHoy($m, $ahora) || $this->difusion->yaSalio($m, "dia:" . $ahora->format("Y-m-d"))) {
            return [];
        }

        return [match ($tipo) {
            TipoMensajePredeterminado::VENTAS_DIA => $this->contenidos->ventasDelDia($m, $ahora),
            TipoMensajePredeterminado::DESEMPENO_DIA => $this->contenidos->desempenoDelDia($m, $ahora),
            default => new Pieza("dia:" . $ahora->format("Y-m-d"), ""),
        }];
    }

    /** @return list<MensajePredeterminado> */
    private function activas(): array
    {
        return $this->em->getRepository(MensajePredeterminado::class)->findBy(["activo" => true], ["id" => "ASC"]);
    }

    /**
     * @param array{envios: int, destinatarios: int, fallidos: int} $r
     * @param callable(): list<int>                                   $envio destinatarios de cada pieza (0: ya había salido)
     */
    private function seguro(MensajePredeterminado $m, array &$r, callable $envio): void
    {
        try {
            foreach ($envio() as $n) {
                if ($n > 0) {
                    $r["envios"]++;
                    $r["destinatarios"] += $n;
                }
            }
        } catch (\Throwable $e) {
            $r["fallidos"]++;
            $this->logger->error("Mensaje predeterminado {id} «{nombre}»: {error}", ["id" => $m->getId(), "nombre" => $m->getNombre(), "error" => $e->getMessage(), "exception" => $e]);
        }
    }
}
