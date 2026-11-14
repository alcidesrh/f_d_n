<?php

declare(strict_types=1);

namespace App\Seguimiento;

use App\Entity\Salida;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Las irregularidades (`Irregularidades`) de los buses que están en recorrido
 * ahora, con las lecturas GPS de su salida. La consulta el aviso del chat
 * «Irregularidad en la trayectoria (GPS)» cada 5 minutos.
 *
 * Umbrales (vacío = el de `UmbralesIrregularidad`): `SEGUIMIENTO_VELOCIDAD_MAXIMA_KMH`,
 * `SEGUIMIENTO_EXCESO_MINUTOS` y `SEGUIMIENTO_PARADA_LARGA_MINUTOS`.
 */
class IrregularidadesEnCurso
{
    /** Solo se dan las que seguían hace menos que esto: las viejas ya se avisaron o ya no importan. */
    public const RECIENTE_SEGUNDOS = 900;
    /** Lecturas que se miran por bus: lo último del recorrido, no el viaje entero. */
    public const MIRAR_SEGUNDOS = 7200;

    private readonly UmbralesIrregularidad $umbrales;

    public function __construct(
        private readonly Trayectorias $trayectorias,
        private readonly HistorialGps $historial,
        private readonly SalidasEnRecorrido $salidas,
        private readonly EntityManagerInterface $em,
        #[Autowire(env: "default::SEGUIMIENTO_VELOCIDAD_MAXIMA_KMH")] ?string $velocidadMaxima = null,
        #[Autowire(env: "default::SEGUIMIENTO_EXCESO_MINUTOS")] ?string $minutosExceso = null,
        #[Autowire(env: "default::SEGUIMIENTO_PARADA_LARGA_MINUTOS")] ?string $minutosParada = null,
    ) {
        $this->umbrales = self::umbralesDe($velocidadMaxima, $minutosExceso, $minutosParada);
    }

    public function umbrales(): UmbralesIrregularidad
    {
        return $this->umbrales;
    }

    /** Lo que venga mal escrito o fuera de rango se ignora: queda el umbral por defecto. */
    public static function umbralesDe(?string $velocidadMaxima, ?string $minutosExceso, ?string $minutosParada): UmbralesIrregularidad
    {
        $d = new UmbralesIrregularidad();
        $entero = static fn (?string $v, int $min, int $max): ?int => null !== $v && ctype_digit(trim($v)) && (int) trim($v) >= $min && (int) trim($v) <= $max ? (int) trim($v) : null;
        $exceso = $entero($minutosExceso, 1, 120);
        $parada = $entero($minutosParada, 1, 720);

        return new UmbralesIrregularidad(
            velocidadMaximaKmh: $entero($velocidadMaxima, 20, 200) ?? $d->velocidadMaximaKmh,
            segundosExceso: null === $exceso ? $d->segundosExceso : $exceso * 60,
            segundosDetenido: null === $parada ? $d->segundosDetenido : $parada * 60,
        );
    }

    /**
     * @return list<array{salida: array{salidaId: int, busId: int, bus: string, empresa: string, rutaNombre: string, desde: int, hasta: int}, irregularidad: Irregularidad}>
     */
    public function deAhora(\DateTimeImmutable $ahora, ?int $empresa = null): array
    {
        $enCurso = $this->trayectorias->enCurso($ahora, $empresa);
        if ([] === $enCurso) {
            return [];
        }
        $instante = $ahora->getTimestamp();
        /** @var list<Salida> $entidades */
        $entidades = $this->em->getRepository(Salida::class)->findBy(['id' => array_column($enCurso, 'salidaId')]);
        $paradas = $this->salidas->paradas($entidades);
        $kilometros = [];
        foreach ($entidades as $s) {
            $kilometros[(int) $s->getId()] = (float) $s->getRuta()?->getKilometros();
        }

        $halladas = [];
        foreach ($enCurso as $s) {
            $desde = max($s['desde'], $instante - self::MIRAR_SEGUNDOS);
            $lecturas = $this->historial->lecturas($s['busId'], $desde, $instante);
            if ([] === $lecturas) {
                continue;
            }
            $trazado = TrazadoDeRuta::trazar($paradas[$s['salidaId']] ?? [], $kilometros[$s['salidaId']] ?? 0.0);
            $puntos = array_map(static fn (array $p): array => [$p['lat'], $p['lng']], $trazado ?? []);
            foreach (self::filtrar(Irregularidades::detectar($lecturas, $puntos, $this->umbrales), $lecturas[0][0], $desde > $s['desde'], [] !== $puntos, $instante) as $i) {
                $halladas[] = ['salida' => $s, 'irregularidad' => $i];
            }
        }

        return $halladas;
    }

    /**
     * De lo detectado, lo que toca avisar ahora:
     * - lo que seguía hace poco (`RECIENTE_SEGUNDOS`);
     * - no las paradas largas si no se pudo ubicar ninguna parada de la ruta (todas parecerían fuera);
     * - no lo que empieza en la primera lectura cuando no se miró el recorrido entero: empezó
     *   antes, ya se avisó, y su clave cambiaría con cada corrida.
     *
     * @param list<Irregularidad> $detectadas
     *
     * @return list<Irregularidad>
     */
    public static function filtrar(array $detectadas, int $primeraLectura, bool $recortado, bool $conParadas, int $ahora): array
    {
        return array_values(array_filter($detectadas, static fn (Irregularidad $i): bool => $i->hasta >= $ahora - self::RECIENTE_SEGUNDOS
            && ($conParadas || Irregularidad::PARADA_LARGA !== $i->tipo)
            && !($recortado && $i->desde === $primeraLectura)));
    }
}
