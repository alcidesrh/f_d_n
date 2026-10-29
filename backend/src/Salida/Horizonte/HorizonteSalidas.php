<?php

declare(strict_types=1);

namespace App\Salida\Horizonte;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Ubicacion;
use App\Chat\Conversaciones;
use App\Entity\EsquemaSalida;
use App\Entity\EsquemaSalidaMomento;
use App\Entity\Usuario;
use App\Salida\Programacion\Programacion;
use App\Salida\ProgramadorSalidas;
use App\Salida\SalidaRechazada;
use App\Salida\VistaSalida;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Mantiene desplegados los esquemas automáticos (ADR-044). Cada tramo es una
 * transacción con el esquema bloqueado: se despliega con el mismo plan que
 * el programador de salidas (las que ya existen no se duplican, las que
 * chocan no se crean) y la frontera avanza en el mismo commit. Así un fallo
 * a mitad deja lo hecho y el reintento sigue desde ahí.
 *
 * - Un conflicto (el bus ya tiene otro viaje) no detiene el esquema: se avisa
 *   por el chat a quienes programan salidas en esa empresa, una sola vez,
 *   porque la frontera ya pasó ese día.
 * - Un rechazo del plan (ruta inactiva, un bus que ya no es de la empresa)
 *   pausa el esquema y se avisa: sigue cuando alguien lo corrige y lo guarda.
 */
final class HorizonteSalidas
{
    /** Tramos por corrida: con el horizonte máximo alcanza para ponerse al día. */
    public const MAX_TRAMOS = 20;
    public const MAX_CONFLICTOS_AVISO = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProgramadorSalidas $programador,
        private readonly Transaccion $transaccion,
        private readonly Conversaciones $chat,
        private readonly Autorizacion $autorizacion,
        private readonly ClockInterface $reloj,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return list<int> los esquemas automáticos que no llegan a su horizonte */
    public function pendientes(): array
    {
        $hoy = $this->hoy();
        $filas = $this->em->createQueryBuilder()
            ->select("e.id", "e.horizonteDias", "e.programadoHasta")
            ->from(EsquemaSalida::class, "e")
            ->where("e.automatico = true AND e.pausadoMotivo IS NULL AND e.ancla IS NOT NULL")
            ->orderBy("e.id")
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(
            static fn(array $f) => (int) $f["id"],
            array_filter($filas, static fn(array $f) => Cadencia::ventana($hoy, (int) $f["horizonteDias"], $f["programadoHasta"]) !== null),
        ));
    }

    /**
     * Despliega desde la frontera hasta el horizonte.
     *
     * @return array<string, mixed>
     */
    public function extender(int $esquemaId): array
    {
        $hoy = $this->hoy();
        $total = ["tramos" => 0, "creadas" => 0, "existentes" => 0, "conflictos" => 0];
        $conflictos = [];
        for ($n = 0; $n < self::MAX_TRAMOS; ++$n) {
            try {
                $hecho = $this->transaccion->ejecutar(function () use ($esquemaId, $hoy): ?array {
                    $e = $this->bloquear($esquemaId);
                    $ventana = $e === null ? null : Cadencia::ventana($hoy, $e->getHorizonteDias(), $e->getProgramadoHasta());
                    if ($e === null || $ventana === null) {
                        return null;
                    }
                    $tramo = Cadencia::tramos($e->getAncla(), $e->getIntervaloDias(), $ventana["desde"], $ventana["hasta"])[0] ?? null;
                    if ($tramo === null) {
                        // Ningún día de la cadencia cae en la ventana: queda al día.
                        $e->desplegadoHasta($ventana["hasta"]);

                        return null;
                    }
                    $hecho = $this->programador->desplegarEsquema($e, $this->programacion($e, $tramo, $e->getMomentos()->toArray(), $hoy));
                    $e->desplegadoHasta($tramo["hasta"]);

                    return $hecho;
                });
            } catch (SalidaRechazada $rechazo) {
                $this->pausar($esquemaId, $rechazo->getMessage());
                $total["pausado"] = $rechazo->getMessage();
                break;
            }
            if ($hecho === null) {
                break;
            }
            $this->sumar($total, $hecho, $conflictos);
        }
        $this->avisarConflictos($esquemaId, $conflictos);

        return $total;
    }

    /**
     * Despliega solo `$horas` (recién agregadas al esquema) desde hoy hasta
     * la frontera; más allá, las pone la extensión de siempre.
     *
     * @param list<string> $horas
     *
     * @return array<string, mixed>
     */
    public function rellenar(int $esquemaId, array $horas): array
    {
        $hoy = $this->hoy();
        $total = ["tramos" => 0, "creadas" => 0, "existentes" => 0, "conflictos" => 0];
        $e = $this->em->find(EsquemaSalida::class, $esquemaId);
        if (!self::activo($e) || $e->getProgramadoHasta() === null || $e->getProgramadoHasta() < $hoy) {
            return $total;
        }
        $conflictos = [];
        foreach (Cadencia::tramos($e->getAncla(), $e->getIntervaloDias(), $hoy, $e->getProgramadoHasta()) as $tramo) {
            try {
                $hecho = $this->transaccion->ejecutar(function () use ($esquemaId, $horas, $tramo, $hoy): ?array {
                    $e = $this->bloquear($esquemaId);
                    $momentos = $e === null ? [] : array_values(array_filter(
                        $e->getMomentos()->toArray(),
                        static fn(EsquemaSalidaMomento $m) => in_array($m->getHora(), $horas, true),
                    ));
                    if ($e === null || $momentos === [] || $e->getProgramadoHasta() < $tramo["desde"]) {
                        return null;
                    }

                    return $this->programador->desplegarEsquema($e, $this->programacion($e, [
                        "desde" => $tramo["desde"],
                        "hasta" => min($tramo["hasta"], $e->getProgramadoHasta()),
                    ], $momentos, $hoy));
                });
            } catch (SalidaRechazada $rechazo) {
                $this->pausar($esquemaId, $rechazo->getMessage());
                $total["pausado"] = $rechazo->getMessage();
                break;
            }
            if ($hecho === null) {
                break;
            }
            $this->sumar($total, $hecho, $conflictos);
        }
        $this->avisarConflictos($esquemaId, $conflictos);

        return $total;
    }

    /** @phpstan-assert-if-true EsquemaSalida $e */
    private static function activo(?EsquemaSalida $e): bool
    {
        return $e !== null && $e->isAutomatico() && $e->getPausadoMotivo() === null && $e->getAncla() !== null;
    }

    /** El esquema bloqueado y leído de nuevo, si sigue automático y sin pausa. */
    private function bloquear(int $id): ?EsquemaSalida
    {
        $e = $this->em->find(EsquemaSalida::class, $id);
        if ($e !== null) {
            $this->em->refresh($e, LockMode::PESSIMISTIC_WRITE);
        }

        return self::activo($e) ? $e : null;
    }

    /**
     * @param array{desde: \DateTimeImmutable, hasta: \DateTimeImmutable} $tramo
     * @param array<EsquemaSalidaMomento>                                 $momentos
     */
    private function programacion(EsquemaSalida $e, array $tramo, array $momentos, \DateTimeImmutable $hoy): Programacion
    {
        return Programacion::desdeArray([
            "rutaId" => $e->getRuta()->getId(),
            "momentos" => array_values(array_map(static fn(EsquemaSalidaMomento $m) => ["hora" => $m->getHora(), "busId" => $m->getBus()->getId()], $momentos)),
            "desde" => $tramo["desde"]->format("Y-m-d"),
            "hasta" => $tramo["hasta"]->format("Y-m-d"),
            "intervaloDias" => $e->getIntervaloDias(),
        ], $hoy);
    }

    /**
     * @param array<string, mixed>                                                         $total
     * @param array{resumen: array<string, int>, conflictos: list<array<string, mixed>>} $hecho
     * @param list<array<string, mixed>>                                                   $conflictos
     */
    private function sumar(array &$total, array $hecho, array &$conflictos): void
    {
        ++$total["tramos"];
        $total["creadas"] += $hecho["resumen"][ProgramadorSalidas::NUEVA];
        $total["existentes"] += $hecho["resumen"][ProgramadorSalidas::EXISTE];
        $total["conflictos"] += $hecho["resumen"][ProgramadorSalidas::CONFLICTO];
        array_push($conflictos, ...$hecho["conflictos"]);
    }

    private function pausar(int $esquemaId, string $motivo): void
    {
        $e = $this->transaccion->ejecutar(function () use ($esquemaId, $motivo): ?EsquemaSalida {
            $e = $this->bloquear($esquemaId);
            $e?->pausar($motivo, \DateTimeImmutable::createFromInterface($this->reloj->now()));

            return $e;
        });
        if ($e === null) {
            return;
        }
        $this->logger->warning("Esquema {esquema} pausado: {motivo}", ["esquema" => $esquemaId, "motivo" => $motivo]);
        $this->avisar($e, sprintf(
            "El esquema automático «%s» (%s) se pausó: %s Corríjalo y guárdelo de nuevo en el programador de salidas para que siga.",
            $e->getNombre(),
            VistaSalida::ruta($e->getRuta()),
            rtrim($motivo, ".") . ".",
        ), []);
    }

    /** @param list<array<string, mixed>> $conflictos */
    private function avisarConflictos(int $esquemaId, array $conflictos): void
    {
        $e = $conflictos === [] ? null : $this->em->find(EsquemaSalida::class, $esquemaId);
        if ($e === null) {
            return;
        }
        $this->avisar($e, self::textoConflictos($e->getNombre(), VistaSalida::ruta($e->getRuta()), $conflictos), array_values(array_map(
            static fn(int $id) => ["tipo" => "Salida", "id" => $id],
            array_slice(array_values(array_unique(array_filter(array_map(static fn(array $c) => $c["choque"]["id"] ?? null, $conflictos)))), 0, 5),
        )));
    }

    /** @param list<array<string, mixed>> $conflictos ítems del plan */
    public static function textoConflictos(string $esquema, string $ruta, array $conflictos): string
    {
        $lineas = [sprintf(
            "El esquema automático «%s» (%s) no creó %d %s porque el bus ya tiene otro viaje a esa hora:",
            $esquema,
            $ruta,
            count($conflictos),
            count($conflictos) === 1 ? "salida" : "salidas",
        )];
        foreach (array_slice($conflictos, 0, self::MAX_CONFLICTOS_AVISO) as $c) {
            $choque = is_array($c["choque"] ?? null) ? $c["choque"] : [];
            $lineas[] = sprintf(
                "• %s · bus %s%s",
                (new \DateTimeImmutable((string) $c["fecha"]))->format("d/m H:i"),
                $c["bus"],
                isset($choque["ruta"]) ? " · ocupado en " . $choque["ruta"] . (isset($choque["fecha"]) ? " (" . (new \DateTimeImmutable((string) $choque["fecha"]))->format("d/m H:i") . ")" : "") : "",
            );
        }
        if (count($conflictos) > self::MAX_CONFLICTOS_AVISO) {
            $lineas[] = sprintf("…y %d más.", count($conflictos) - self::MAX_CONFLICTOS_AVISO);
        }
        $lineas[] = "Esos días no se vuelven a intentar: programe esas salidas a mano o cambie el bus del esquema.";

        return implode("\n", $lineas);
    }

    /** @param list<array{tipo: string, id: int}> $tarjetas */
    private function avisar(EsquemaSalida $e, string $texto, array $tarjetas): void
    {
        $donde = Ubicacion::empresa($e->getEmpresa()?->getId());
        $usuarios = array_values(array_map(
            static fn(array $r): Usuario => $r["usuario"],
            array_filter($this->autorizacion->deTodos(), static fn(array $r) => $r["permisos"]->puede(Capacidad::SALIDA_CREAR->value, $donde)),
        ));
        if ($usuarios === []) {
            return;
        }
        try {
            $this->chat->avisarSistema($usuarios, $texto, $tarjetas);
        } catch (\Throwable $ex) {
            // El aviso no deshace lo desplegado.
            $this->logger->error("No se pudo avisar del esquema {esquema}: {error}", ["esquema" => $e->getId(), "error" => $ex->getMessage(), "exception" => $ex]);
        }
    }

    private function hoy(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->reloj->now())->setTime(0, 0);
    }
}
