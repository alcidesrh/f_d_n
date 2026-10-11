<?php

declare(strict_types=1);

namespace App\Venta;

use App\Croquis\CroquisBus;
use App\Entity\Asiento;
use App\Entity\Enclave;
use App\Entity\Enum\AsientoClase;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\PrecisionHora;
use App\Entity\Salida;
use App\Venta\Boleto\DatosBoleto;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Lecturas de la pantalla de venta y de la página web: salidas del día,
 * paradas, trayectos vendibles, croquis y ocupación. Solo lectura.
 */
final class ConsultaVenta
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Itinerarios $itinerarios,
        private readonly Disponibilidad $disponibilidad,
        private readonly ResolutorTarifa $tarifas,
        private readonly ReglasVenta $reglas,
        private readonly CroquisBus $croquis,
        private readonly HorasSalida $horas,
        private readonly ClockInterface $reloj,
        private readonly EnLinea\AjustesPagina $ajustes,
        private readonly BuscadorSalidas $buscador,
    ) {}

    /**
     * Salidas que pasan por la estación (en cualquier parada salvo la
     * última) **a una hora del día pedido** —la hora en la estación, no la
     * del origen del bus (ADR-030)— con algún trayecto tarifado que sube ahí.
     * Sin estación, por la hora en su origen. Con empresa, solo las suyas
     * (agencias que venden para una sola empresa). Con `$hasta` se buscan
     * entre `$dia` (con su hora) y `$hasta`, ambos inclusive; con
     * `$destinoId`, las que llegan a ese destino después de la estación.
     * `vendidos` cuenta los asientos ocupados en el tramo que se ofrece.
     *
     * @return list<array<string, mixed>>
     */
    public function salidasDeEstacion(\DateTimeImmutable $dia, ?int $estacionId, ?int $empresaId = null, bool $incluirAnuladas = true, ?int $destinoId = null, ?\DateTimeImmutable $hasta = null): array
    {
        [$desde, $hastaExclusivo] = $hasta === null
            ? [$dia->setTime(0, 0), $dia->setTime(0, 0)->modify("+1 day")]
            : [$dia, $hasta->modify("+1 minute")];
        $pasan = $this->buscador->pasanPor($desde, $hastaExclusivo, $estacionId, $destinoId);
        $salidas = array_filter(
            $this->salidas(array_column($pasan, "salida")),
            static fn(Salida $r) => ($empresaId === null || $r->getEmpresa()?->getId() === $empresaId)
                && ($incluirAnuladas || $r->getEstado() !== EstadoSalida::CANCELADA),
        );
        $itinerarios = $this->itinerarios->deSalidas($salidas);
        $asientos = $this->asientosPorBus(array_values($salidas));

        $filas = [];
        $tramos = [];
        foreach ($pasan as $p) {
            $r = $salidas[$p["salida"]] ?? null;
            if ($r === null) {
                continue;
            }
            $it = $itinerarios[$r->getId()];
            $subeEn = $it->paradas[$p["sube"]];
            $clases = $r->getBus() !== null ? array_keys($asientos[$r->getBus()->getId()] ?? []) : self::todasLasClases();
            $sube = array_filter(
                $this->tarifados($r, $it, $clases),
                static fn(int $id) => $it->trayectos()[$id]["origen"] === $subeEn,
                ARRAY_FILTER_USE_KEY,
            );
            if ($sube === []) {
                continue;
            }
            ["trayecto" => $ofrecido, "precios" => $precios] = $this->precios($r, $it, $subeEn, $destinoId, $sube, $clases);
            $tramo = ($ofrecido !== null ? $it->tramo($ofrecido) : null) ?? new Tramo($p["sube"], $p["baja"] ?? count($it->paradas) - 1);
            $tramos[] = ["salida" => (int) $r->getId(), "sube" => $tramo->desde, "baja" => $tramo->hasta];
            $filas[] = [$r, $it, $estacionId, $precios, $tramo];
        }
        $vendidos = $this->buscador->vendidosEnTramos($tramos);
        $capacidad = array_map(static fn(array $porClase) => array_sum($porClase), $asientos);

        $nombres = $this->nombres(array_merge([], ...array_map(static fn(array $f) => [$f[1]->paradas[$f[4]->desde], $f[1]->paradas[$f[4]->hasta]], $filas)));

        return array_map(fn(array $f) => [
            ...$this->resumen($f[0], $f[1], $f[2], $vendidos, $capacidad),
            "precios" => $f[3],
            "viaje" => self::viaje($f[1], $f[4], $nombres),
        ], $filas);
    }

    /**
     * Salidas a la venta en línea entre dos paradas, con la hora en el
     * origen dentro del día: la web necesita que exista el trayecto
     * origen→destino (con tarifa).
     *
     * @return list<array<string, mixed>>
     */
    public function salidasEnLinea(\DateTimeImmutable $dia, int $origenId, int $destinoId): array
    {
        $ahora = $this->reloj->now();
        $pasan = $this->buscador->pasanPor($dia->setTime(0, 0), $dia->setTime(0, 0)->modify("+1 day"), $origenId, $destinoId);
        $salidas = array_filter(
            $this->salidas(array_column($pasan, "salida")),
            fn(Salida $r) => $r->getEstado() === EstadoSalida::PROGRAMADA
                && $r->getBus() !== null
                && $ahora < $this->reglas->cierreEnLinea($r),
        );
        $itinerarios = $this->itinerarios->deSalidas($salidas);

        $resultado = [];
        foreach ($pasan as $p) {
            $r = $salidas[$p["salida"]] ?? null;
            if ($r === null) {
                continue;
            }
            $it = $itinerarios[$r->getId()];
            $trayectoId = $it->trayectoEntre($origenId, $destinoId);
            if ($trayectoId === null) {
                continue;
            }
            $tramo = new Tramo($p["sube"], (int) $p["baja"]);
            $estados = $this->disponibilidad->estados($r, $tramo);
            $asientos = $r->getBus()->getAsientos();
            $clases = array_values(array_unique(array_map(static fn(Asiento $a) => $a->getClase()->value, $asientos->toArray())));
            $trayecto = $this->em->getReference(\App\Entity\Trayecto::class, $trayectoId);
            $tarifas = $this->tarifas->porClase($r, $trayecto, $clases);
            if ($tarifas === []) {
                continue;
            }
            $recargo = $this->ajustes->recargo();
            $porClase = array_map(static fn(CandidatoTarifa $c) => $recargo->aplicar($c->precio), $tarifas);
            $precios = array_values($porClase);
            usort($precios, static fn($a, $b) => $a->compare($b));
            $ocupados = count(array_filter($estados, static fn(array $e) => $e["estado"] !== Ocupacion::PROPIO));
            $reservados = count(array_filter($estados, static fn(array $e) => $e["estado"] === Ocupacion::RESERVADO));

            $resultado[] = [
                "id" => $r->getId(),
                "trayecto" => $trayectoId,
                "salida" => $this->horaEn($r, $it, $origenId),
                "llegada" => $this->horaEn($r, $it, $destinoId),
                "salidaInicio" => $r->getFecha()->format(DATE_ATOM),
                "empresa" => $r->getEmpresa()?->getAlias() ?: $r->getEmpresa()?->getNombre(),
                "ruta" => $r->getRuta()->getPuntas(),
                "rutaOrigen" => $r->getRuta()->getOrigen()?->getNombre(),
                // La hora en la parada del cliente es estimada salvo en el origen del bus.
                "subeEnOrigen" => $tramo->desde === 0,
                "clases" => array_map(
                    static function (string $c, $precio) use ($asientos, $estados) {
                        $deClase = array_filter($asientos->toArray(), static fn(Asiento $a) => $a->getClase()->value === $c);

                        return [
                            "clase" => $c,
                            "precio" => DatosBoleto::importe($precio),
                            "asientos" => count($deClase),
                            "ocupados" => count(array_filter($deClase, static fn(Asiento $a) => isset($estados[$a->getId()]) && $estados[$a->getId()]["estado"] !== Ocupacion::PROPIO)),
                        ];
                    },
                    array_keys($porClase),
                    $porClase,
                ),
                "desde" => DatosBoleto::importe($precios[0]),
                "capacidad" => $asientos->count(),
                "ocupados" => $ocupados,
                "reservados" => $reservados,
                "disponibles" => max(0, $asientos->count() - $ocupados),
                "bus" => $r->getBus()->getGama(),
                "paradas" => max(0, $it->posicion($destinoId) - $it->posicion($origenId) - 1),
                "cierre" => $this->reglas->cierreEnLinea($r)->format(DATE_ATOM),
            ];
        }

        return $resultado;
    }

    /**
     * Paradas, trayectos vendibles y croquis de un salida.
     *
     * Vendibles son los trayectos de la salida (pares de sus paradas) que tienen
     * tarifa —propia o asignable con lo que heredan de la salida: empresa,
     * bus, clase de bus y hora— para alguna clase de asiento del bus; cada
     * uno dice para qué clases (`clases`). Lo mismo rige en taquilla,
     * agencias y página web (`ReglasVenta::cotizar` lo exige al vender).
     *
     * @return array<string, mixed>
     */
    public function detalle(Salida $salida): array
    {
        $it = $this->itinerarios->deSalida($salida);
        $enclaves = $this->enclaves($it->paradas);
        $croquis = $salida->getBus() !== null ? $this->croquis->leer($salida->getBus())["elementos"] : [];
        // Sin bus asignado aún no hay asientos: se informa con todas las clases.
        $clasesBus = $salida->getBus() !== null
            ? array_values(array_unique(array_column(
                array_filter($croquis, static fn(array $e) => $e["tipo"] === "asiento"),
                "clase",
            )))
            : self::todasLasClases();

        $trayectos = [];
        foreach ($this->tarifados($salida, $it, $clasesBus) as $id => $clases) {
            $t = $it->trayectos()[$id];
            $trayectos[] = [
                "id" => $id,
                "origen" => $t["origen"],
                "destino" => $t["destino"],
                "completo" => $id === $it->trayectoId,
                "clases" => $clases,
            ];
        }
        usort($trayectos, static fn($a, $b) => [$it->posicion($a["origen"]), $it->posicion($a["destino"])] <=> [$it->posicion($b["origen"]), $it->posicion($b["destino"])]);

        return [
            ...$this->resumen($salida, $it, null),
            "paradas" => array_map(fn(int $id, int $pos) => [
                "id" => $id,
                "nombre" => $enclaves[$id]?->getNombre(),
                "direccion" => $enclaves[$id]?->getDireccion(),
                "posicion" => $pos,
                "hora" => $this->horaEn($salida, $it, $id),
            ], $it->paradas, array_keys($it->paradas)),
            "trayectos" => $trayectos,
            "croquis" => $croquis,
            "cierreEnLinea" => $this->reglas->cierreEnLinea($salida)->format(DATE_ATOM),
        ];
    }

    /**
     * Estado de los asientos ocupados para un tramo (los libres no aparecen).
     *
     * `$conCanal` (taquilla): por qué canal se vendió y si fue sin cobro
     * (cortesía, voucher). La página web solo ve libre u ocupado.
     *
     * @return list<array{asiento: int, estado: string, canal: ?string, sinCobro: ?string}>
     */
    public function ocupacion(Salida $salida, Tramo $tramo, ?string $tokenPropio = null, bool $conCanal = true): array
    {
        $estados = $this->disponibilidad->estados($salida, $tramo, $tokenPropio);
        $lista = [];
        foreach ($estados as $asiento => $e) {
            $lista[] = [
                "asiento" => $asiento,
                "estado" => $e["estado"],
                "canal" => $conCanal ? $e["canal"] : null,
                "sinCobro" => $conCanal ? $e["sinCobro"] : null,
            ];
        }

        return $lista;
    }

    /**
     * Asientos libres que conviene vender para el tramo (`Recomendacion`).
     *
     * @return list<int>
     */
    public function recomendados(Salida $salida, Tramo $tramo): array
    {
        if ($salida->getBus() === null) {
            return [];
        }

        return Recomendacion::asientos(
            array_map(static fn(Asiento $a) => (int) $a->getId(), $salida->getBus()->getAsientos()->toArray()),
            count($this->itinerarios->deSalida($salida)->paradas) - 1,
            $tramo,
            $this->disponibilidad->ocupantes($salida),
        );
    }

    /**
     * Pares que se pueden vender (ADR-030): trayecto activo con alguna tarifa
     * cuyas puntas son paradas de una ruta activa, en orden, donde se puede
     * subir y bajar.
     */
    private const PARES_VENDIBLES = "SELECT DISTINCT t.origen_id, t.destino_id
          FROM trayecto t
          JOIN parada po ON po.enclave_id = t.origen_id AND po.permite_subir
          JOIN parada pd ON pd.ruta_id = po.ruta_id AND pd.enclave_id = t.destino_id AND pd.orden > po.orden AND pd.permite_bajar
          JOIN ruta r ON r.id = po.ruta_id AND r.activo
         WHERE t.activo AND EXISTS (SELECT 1 FROM boleto_tarifa bt WHERE bt.trayecto_id = t.id)";

    /**
     * Enclaves desde donde se puede comprar algún viaje (para la web).
     *
     * @return list<array{id: int, nombre: string, departamento: ?string}>
     */
    public function estacionesEnLinea(): array
    {
        return self::lugares($this->em->getConnection()->fetchAllAssociative(
            "SELECT e.id, e.nombre, e.departamento FROM enclave e
              WHERE e.id IN (SELECT origen_id FROM (" . self::PARES_VENDIBLES . ") p)
              ORDER BY e.nombre",
        ));
    }

    /**
     * Estaciones de la página web (donde se sube o se baja en algún viaje
     * que se vende), con dirección, departamento y ubicación.
     *
     * @return list<array{id: int, nombre: string, direccion: ?string, departamento: ?string, latitud: ?float, longitud: ?float}>
     */
    public function directorioEstaciones(): array
    {
        return array_map(
            static fn(array $f) => [
                "id" => (int) $f["id"],
                "nombre" => $f["nombre"],
                "direccion" => $f["direccion"] !== null ? trim(preg_replace('/\s+/', " ", $f["direccion"])) : null,
                "departamento" => $f["departamento"],
                "latitud" => $f["latitud"] !== null ? (float) $f["latitud"] : null,
                "longitud" => $f["longitud"] !== null ? (float) $f["longitud"] : null,
            ],
            $this->em->getConnection()->fetchAllAssociative(
                "SELECT e.id, e.nombre, e.direccion, e.departamento, e.latitud, e.longitud FROM enclave e
                  WHERE e.tipo = 'estacion'
                    AND e.id IN (SELECT origen_id FROM (" . self::PARES_VENDIBLES . ") p UNION SELECT destino_id FROM (" . self::PARES_VENDIBLES . ") q)
                  ORDER BY e.departamento, e.nombre",
            ),
        );
    }

    /**
     * Destinos a los que se puede comprar un viaje desde un enclave.
     *
     * @return list<array{id: int, nombre: string, departamento: ?string}>
     */
    public function destinosDesde(int $origenId): array
    {
        return self::lugares($this->em->getConnection()->fetchAllAssociative(
            "SELECT e.id, e.nombre, e.departamento FROM enclave e
              WHERE e.id IN (SELECT destino_id FROM (" . self::PARES_VENDIBLES . ") p WHERE p.origen_id = :origen)
              ORDER BY e.nombre",
            ["origen" => $origenId],
        ));
    }

    /**
     * @param list<array<string, mixed>> $filas
     *
     * @return list<array{id: int, nombre: string, departamento: ?string}>
     */
    private static function lugares(array $filas): array
    {
        return array_map(
            static fn(array $f) => ["id" => (int) $f["id"], "nombre" => $f["nombre"], "departamento" => $f["departamento"]],
            $filas,
        );
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, Salida> por id (las que el filtro de empresa oculta no aparecen)
     */
    private function salidas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $porId = [];
        foreach ($this->em->createQueryBuilder()
            ->select("r", "ru", "b", "e")
            ->from(Salida::class, "r")
            ->join("r.ruta", "ru")
            ->leftJoin("r.bus", "b")
            ->leftJoin("r.empresa", "e")
            ->where("r.id IN (:ids)")
            ->setParameter("ids", $ids)
            ->getQuery()
            ->getResult() as $r) {
            $porId[$r->getId()] = $r;
        }

        return $porId;
    }

    /**
     * @param array<int, int> $ocupados
     * @param array<int, int> $capacidad
     *
     * @return array<string, mixed>
     */
    private function resumen(Salida $r, Itinerario $it, ?int $estacionId, array $ocupados = [], array $capacidad = []): array
    {
        $busId = $r->getBus()?->getId();

        return [
            "id" => $r->getId(),
            "salida" => $r->getFecha()->format(DATE_ATOM),
            "salidaEstacion" => $estacionId !== null ? $this->horaEn($r, $it, $estacionId) : null,
            "estado" => $r->getEstado()->value,
            "empresa" => $r->getEmpresa() === null ? null : ["id" => $r->getEmpresa()->getId(), "nombre" => $r->getEmpresa()->getAlias() ?? $r->getEmpresa()->getNombre()],
            "bus" => $r->getBus() === null ? null : ["id" => $busId, "codigo" => $r->getBus()->getCodigo(), "gama" => $r->getBus()->getGama()],
            "ruta" => [
                "id" => $r->getRuta()->getId(),
                "nombre" => $r->getRuta()->getNombre(),
                "origen" => ["id" => $r->getRuta()->getOrigen()?->getId(), "nombre" => $r->getRuta()->getOrigen()?->getNombre()],
                "destino" => ["id" => $r->getRuta()->getDestino()?->getId(), "nombre" => $r->getRuta()->getDestino()?->getNombre()],
            ],
            "vendidos" => $ocupados[$r->getId()] ?? 0,
            "capacidad" => $busId !== null ? ($capacidad[$busId] ?? null) : null,
        ];
    }

    /**
     * El viaje del cliente dentro de la salida: dónde sube y dónde baja, a
     * qué hora (null si no se conoce; `aprox` si es estimada) y cuántas
     * paradas hay en medio.
     *
     * @param array<int, string> $nombres por id de enclave
     *
     * @return array{sube: array<string, mixed>, baja: array<string, mixed>, intermedias: int}
     */
    private static function viaje(Itinerario $it, Tramo $tramo, array $nombres): array
    {
        $parada = static function (int $posicion) use ($it, $nombres): array {
            $id = $it->paradas[$posicion];
            $h = $it->hora($id);

            return [
                "id" => $id,
                "nombre" => $nombres[$id] ?? null,
                "hora" => $h !== null && $h["precision"] !== PrecisionHora::DESCONOCIDA ? $h["hora"]->format(DATE_ATOM) : null,
                "aprox" => $h !== null && $h["precision"] === PrecisionHora::ESTIMADA,
            ];
        };

        return ["sube" => $parada($tramo->desde), "baja" => $parada($tramo->hasta), "intermedias" => $tramo->hasta - $tramo->desde - 1];
    }

    /**
     * @param list<?int> $ids
     *
     * @return array<int, string> nombre por id de enclave
     */
    private function nombres(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? [] : array_map("strval", $this->em->getConnection()->fetchAllKeyValue(
            "SELECT id, nombre FROM enclave WHERE id IN (:ids)",
            ["ids" => $ids],
            ["ids" => ArrayParameterType::INTEGER],
        ));
    }

    /** Hora estimada del salida en una parada (ISO), si se conoce la duración. */
    private function horaEn(Salida $r, Itinerario $it, int $enclaveId): ?string
    {
        return $this->horas->enParada($r, $enclaveId)?->format(DATE_ATOM);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, ?Enclave>
     */
    private function enclaves(array $ids): array
    {
        $porId = array_fill_keys($ids, null);
        foreach ($this->em->getRepository(Enclave::class)->findBy(["id" => $ids]) as $e) {
            $porId[$e->getId()] = $e;
        }

        return $porId;
    }

    /**
     * Trayectos de la salida (pares de sus paradas) con tarifa para
     * alguna de las clases de asiento dadas, con esas clases.
     *
     * @param list<string> $clases
     *
     * @return array<int, list<string>> por id de trayecto
     */
    private function tarifados(Salida $salida, Itinerario $it, array $clases): array
    {
        $tarifados = [];
        foreach ($this->tarifas->porTrayectos($salida, array_keys($it->trayectos()), $clases) as $id => $porClase) {
            if ($porClase !== []) {
                $deTrayecto = array_keys($porClase);
                sort($deTrayecto);
                $tarifados[$id] = $deTrayecto;
            }
        }

        return $tarifados;
    }

    /**
     * Precio por clase de asiento del tramo que ofrece la lista de salidas:
     * desde la estación (o el origen) hasta el destino elegido (o el final).
     * Sin destino elegido y sin tarifa hasta el final, el tramo con tarifa
     * más largo que sale de la estación.
     *
     * @param array<int, list<string>> $sube    trayectos con tarifa que salen de la estación
     * @param list<string>             $clases
     *
     * @return array{trayecto: ?int, precios: list<array{clase: string, precio: array<string, mixed>|null}>} el trayecto ofrecido y su precio por clase (A, B…); vacío si no hay
     */
    private function precios(Salida $r, Itinerario $it, int $desde, ?int $destinoId, array $sube, array $clases): array
    {
        $id = $it->trayectoEntre($desde, $destinoId ?? $it->paradas[count($it->paradas) - 1]);
        if (($id === null || !isset($sube[$id])) && $destinoId === null) {
            $id = null;
            $lejos = -1;
            foreach (array_keys($sube) as $t) {
                $tramo = $it->trayectos()[$t];
                $pos = $it->posicion($tramo["destino"]) ?? -1;
                if ($tramo["origen"] === $desde && $pos > $lejos) {
                    [$id, $lejos] = [$t, $pos];
                }
            }
        }
        if ($id === null || !isset($sube[$id])) {
            return ["trayecto" => null, "precios" => []];
        }
        $porClase = $this->tarifas->porTrayectos($r, [$id], $clases)[$id] ?? [];
        ksort($porClase);

        return ["trayecto" => $id, "precios" => array_map(
            static fn(string $clase, CandidatoTarifa $t) => ["clase" => $clase, "precio" => DatosBoleto::importe($t->precio)],
            array_keys($porClase),
            array_values($porClase),
        )];
    }

    /** @return list<string> */
    private static function todasLasClases(): array
    {
        return array_map(static fn(AsientoClase $c) => $c->value, AsientoClase::cases());
    }

    /**
     * @param list<Salida> $salidas
     *
     * @return array<int, array<string, int>> asientos por bus y clase
     */
    private function asientosPorBus(array $salidas): array
    {
        $buses = array_values(array_unique(array_filter(array_map(static fn(Salida $r) => $r->getBus()?->getId(), $salidas))));
        if ($buses === []) {
            return [];
        }
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(a.bus) AS bus", "a.clase AS clase", "COUNT(a.id) AS n")
            ->from(Asiento::class, "a")
            ->where("a.bus IN (:buses)")
            ->groupBy("a.bus", "a.clase")
            ->setParameter("buses", $buses)
            ->getQuery()
            ->getArrayResult();

        $porBus = [];
        foreach ($filas as $f) {
            $clase = $f["clase"] instanceof \BackedEnum ? $f["clase"]->value : (string) $f["clase"];
            $porBus[(int) $f["bus"]][$clase] = (int) $f["n"];
        }

        return $porBus;
    }
}
