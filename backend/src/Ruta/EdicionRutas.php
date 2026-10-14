<?php

declare(strict_types=1);

namespace App\Ruta;

use App\Entity\BusClase;
use App\Entity\Enclave;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Parada;
use App\Entity\ParadaTiempo;
use App\Entity\Ruta;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Alta y edición de rutas con sus paradas (ADR-030).
 *
 * Al guardar: existe el `Trayecto` de cada par de paradas en orden (es lo que
 * se tarifa y se vende) y las salidas futuras programadas toman el horario
 * nuevo. Las que ya tienen boletos o reservas conservan sus paradas (solo se
 * recalculan las horas, `Horarios`): un boleto nunca cambia de tramo.
 */
final class EdicionRutas
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Horarios $horarios,
        private readonly Trayectos $trayectos,
        private readonly KilometrosCarretera $carretera,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        /** @var list<Ruta> $rutas */
        $rutas = $this->em->createQueryBuilder()
            ->select("r", "p", "e", "t")
            ->from(Ruta::class, "r")
            ->leftJoin("r.paradas", "p")
            ->leftJoin("p.enclave", "e")
            ->leftJoin("p.tiempos", "t")
            ->getQuery()
            ->getResult();
        $futuras = $this->em->getConnection()->fetchAllKeyValue(
            "SELECT ruta_id, COUNT(*) FROM salida WHERE fecha > NOW() AND estado = :p GROUP BY ruta_id",
            ["p" => EstadoSalida::PROGRAMADA->value],
        );
        usort($rutas, static fn(Ruta $a, Ruta $b) => strnatcasecmp($a->getNombre(), $b->getNombre()));

        return array_map(static fn(Ruta $r) => [
            "id" => (int) $r->getId(),
            "nombre" => $r->getNombre(),
            "origen" => $r->getOrigen()?->getNombre(),
            "destino" => $r->getDestino()?->getNombre(),
            "paradas" => $r->getParadas()->count(),
            "duracionMinutos" => $r->getDuracionMinutos(),
            "kilometros" => $r->getKilometros(),
            "activo" => $r->isActivo(),
            "codigo" => $r->getLegacyId(),
            "salidasFuturas" => (int) ($futuras[$r->getId()] ?? 0),
        ], $rutas);
    }

    /** Lugares y clases de bus para el editor. @return array<string, mixed> */
    public function opciones(): array
    {
        return [
            "lugares" => array_map(static fn(array $f) => [
                "id" => (int) $f["id"],
                "nombre" => $f["nombre"],
                "departamento" => $f["departamento"],
                "latitud" => $f["latitud"] !== null ? (float) $f["latitud"] : null,
                "longitud" => $f["longitud"] !== null ? (float) $f["longitud"] : null,
            ], $this->em->getConnection()->fetchAllAssociative(
                "SELECT id, nombre, departamento, latitud, longitud FROM enclave ORDER BY nombre",
            )),
            "clases" => array_map(
                static fn(BusClase $c) => ["id" => (int) $c->getId(), "nombre" => $c->getNombre()],
                $this->em->getRepository(BusClase::class)->findBy([], ["nombre" => "ASC"]),
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function leer(int $id): array
    {
        $ruta = $this->ruta($id);
        $conteo = $this->em->getConnection()->fetchAssociative(
            "SELECT COUNT(*) AS futuras,
                    COUNT(*) FILTER (WHERE EXISTS (SELECT 1 FROM boleto_asiento b WHERE b.salida_id = s.id AND b.estado NOT IN ('anulado', 'reasignado'))) AS con_boletos
               FROM salida s WHERE s.ruta_id = :r AND s.fecha > NOW() AND s.estado = :p",
            ["r" => $id, "p" => EstadoSalida::PROGRAMADA->value],
        );

        return [
            "id" => (int) $ruta->getId(),
            "nombre" => $ruta->getNombre(),
            "kilometros" => $ruta->getKilometros(),
            "activo" => $ruta->isActivo(),
            "codigo" => $ruta->getLegacyId(),
            "paradas" => array_map(static fn(Parada $p) => [
                "enclaveId" => (int) $p->getEnclave()->getId(),
                "nombre" => $p->getEnclave()->getNombre(),
                "minutos" => $p->getMinutos(),
                "km" => $p->getKm(),
                "permiteSubir" => $p->permiteSubir(),
                "permiteBajar" => $p->permiteBajar(),
                "tiempos" => array_values(array_map(
                    static fn(ParadaTiempo $t) => ["claseId" => (int) $t->getBusClase()->getId(), "minutos" => $t->getMinutos()],
                    $p->getTiempos()->toArray(),
                )),
            ], array_values($ruta->getParadas()->toArray())),
            "salidasFuturas" => (int) $conteo["futuras"],
            "salidasConBoletos" => (int) $conteo["con_boletos"],
            "avisos" => (object) Paradas::avisos(array_map(static fn(Parada $p) => ["minutos" => $p->getMinutos()], array_values($ruta->getParadas()->toArray()))),
        ];
    }

    /**
     * Crea (`$id` nulo) o reemplaza una ruta: `{ nombre, kilometros?, activo?, paradas }`.
     *
     * @param array<string, mixed> $datos
     *
     * @return array<string, mixed> la ruta leída de nuevo, con `kilometrosSinCalcular` (tramos sin km por carretera)
     */
    public function guardar(?int $id, array $datos): array
    {
        $nombre = trim((string) ($datos["nombre"] ?? ""));
        if ($nombre === "" || mb_strlen($nombre) > 255) {
            throw new RutaRechazada("La ruta necesita un nombre de 1 a 255 caracteres.", "nombre_invalido");
        }
        $paradas = Paradas::desdeArray($datos["paradas"] ?? null);
        $kilometros = isset($datos["kilometros"]) && $datos["kilometros"] !== "" ? max(0, (int) $datos["kilometros"]) : null;
        // Antes de la transacción: las consultas HTTP no deben retener el bloqueo de la ruta.
        $carretera = $this->kilometrosDeCarretera($paradas, $kilometros);
        $paradas = $carretera["paradas"];
        $kilometros = $carretera["kilometros"];

        $rutaId = $this->em->wrapInTransaction(function () use ($id, $nombre, $paradas, $kilometros, $datos): int {
            $ruta = $id === null ? new Ruta() : $this->ruta($id, LockMode::PESSIMISTIC_WRITE);
            $ruta->setNombre($nombre)->setKilometros($kilometros)->setActivo((bool) ($datos["activo"] ?? true));
            $id === null && $this->em->persist($ruta);
            $this->reemplazarParadas($ruta, $paradas);
            $this->em->flush();
            $this->trayectos->asegurarDeRuta((int) $ruta->getId());
            $this->horarios->fijar(...$this->salidasFuturas((int) $ruta->getId()));

            return (int) $ruta->getId();
        });
        $this->em->clear();

        return [...$this->leer($rutaId), "kilometrosSinCalcular" => $carretera["sinCalcular"]];
    }

    /**
     * Km por carretera de cada parada. Si todos los tramos se
     * calculan, reemplazan los km de las paradas y el total de la ruta. Si
     * falta alguno, la ruta conserva los que escribió la persona y se avisa
     * qué tramos quedaron sin calcular.
     *
     * @param list<array{enclaveId: int, minutos: ?int, km: ?int, permiteSubir: bool, permiteBajar: bool, tiempos: array<int, int>}> $paradas
     *
     * @return array{paradas: list<array{enclaveId: int, minutos: ?int, km: ?int, permiteSubir: bool, permiteBajar: bool, tiempos: array<int, int>}>, kilometros: ?int, sinCalcular: list<string>}
     */
    private function kilometrosDeCarretera(array $paradas, ?int $kilometros): array
    {
        $enclaves = $this->porId(Enclave::class, array_column($paradas, "enclaveId"), "Un lugar de la ruta no existe.");
        $tramos = [];
        $sinCalcular = [];
        for ($i = 1; $i < count($paradas); $i++) {
            $a = $enclaves[$paradas[$i - 1]["enclaveId"]];
            $b = $enclaves[$paradas[$i]["enclaveId"]];
            $km = $this->carretera->entre(
                self::coordenada($a->getLatitud()), self::coordenada($a->getLongitud()),
                self::coordenada($b->getLatitud()), self::coordenada($b->getLongitud()),
            );
            $tramos[] = $km;
            $km === null && $sinCalcular[] = sprintf("%s → %s", $a->getNombre(), $b->getNombre());
        }
        $acumulados = KilometrosCarretera::acumular($tramos);
        if ($acumulados === null) {
            return ["paradas" => $paradas, "kilometros" => $kilometros, "sinCalcular" => $sinCalcular];
        }
        foreach ($paradas as $i => $p) {
            $paradas[$i]["km"] = $acumulados[$i];
        }

        return ["paradas" => $paradas, "kilometros" => end($acumulados), "sinCalcular" => []];
    }

    private static function coordenada(?string $valor): ?float
    {
        return $valor !== null && is_numeric($valor) ? (float) $valor : null;
    }

    /** Copia de una ruta; con `$invertir`, la de regreso. @return array<string, mixed> */
    public function duplicar(int $id, bool $invertir): array
    {
        $original = $this->leer($id);
        $paradas = Paradas::desdeArray($original["paradas"]);
        $nombre = $invertir
            ? sprintf("%s → %s", end($original["paradas"])["nombre"], $original["paradas"][0]["nombre"])
            : "Copia de " . $original["nombre"];

        return $this->guardar(null, [
            "nombre" => mb_substr($nombre, 0, 255),
            "kilometros" => $original["kilometros"],
            "activo" => false,
            "paradas" => array_map(static fn(array $p) => [
                ...$p,
                "tiempos" => array_map(static fn(int $clase, int $m) => ["claseId" => $clase, "minutos" => $m], array_keys($p["tiempos"]), $p["tiempos"]),
            ], $invertir ? Paradas::invertir($paradas) : $paradas),
        ]);
    }

    /** Solo una ruta sin salidas ni esquemas; si no, se desactiva. */
    public function eliminar(int $id): void
    {
        $ruta = $this->ruta($id);
        $usos = (int) $this->em->getConnection()->fetchOne(
            "SELECT (SELECT COUNT(*) FROM salida WHERE ruta_id = :r) + (SELECT COUNT(*) FROM esquema_salida WHERE ruta_id = :r)",
            ["r" => $id],
        );
        if ($usos > 0) {
            throw new RutaRechazada("La ruta tiene salidas o esquemas: desactívela en vez de eliminarla.", "ruta_en_uso", 409);
        }
        $this->em->remove($ruta);
        $this->em->flush();
    }

    /**
     * Con los mismos lugares en el mismo orden se actualiza cada parada; si
     * cambió la secuencia, se reemplazan (primero se borran: `orden` es único).
     *
     * @param list<array{enclaveId: int, minutos: ?int, km: ?int, permiteSubir: bool, permiteBajar: bool, tiempos: array<int, int>}> $paradas
     */
    private function reemplazarParadas(Ruta $ruta, array $paradas): void
    {
        $enclaves = $this->porId(Enclave::class, array_column($paradas, "enclaveId"), "Un lugar de la ruta no existe.");
        $clases = $this->porId(BusClase::class, array_merge(...array_map(static fn(array $p) => array_keys($p["tiempos"]), $paradas)), "Una clase de bus no existe.");

        $actuales = array_values($ruta->getParadas()->toArray());
        $mismos = array_map(static fn(Parada $p) => (int) $p->getEnclave()->getId(), $actuales) === array_column($paradas, "enclaveId");
        if (!$mismos) {
            $ruta->getParadas()->clear();
            $this->em->flush();
            $actuales = [];
            foreach ($paradas as $orden => $p) {
                $ruta->getParadas()->add($actuales[] = new Parada($ruta, $enclaves[$p["enclaveId"]], $orden));
            }
        }
        foreach ($paradas as $orden => $p) {
            $parada = $actuales[$orden]
                ->setMinutos($p["minutos"])
                ->setKm($p["km"])
                ->setPermiteSubir($p["permiteSubir"])
                ->setPermiteBajar($p["permiteBajar"]);
            // Se actualizan en su lugar: borrar e insertar la misma clase choca con la única (parada, clase).
            $quedan = array_filter($p["tiempos"], static fn(int $m) => $m !== $p["minutos"]);
            foreach ($parada->getTiempos()->toArray() as $t) {
                $clase = (int) $t->getBusClase()->getId();
                if (isset($quedan[$clase])) {
                    $t->setMinutos($quedan[$clase]);
                    unset($quedan[$clase]);
                } else {
                    $parada->getTiempos()->removeElement($t);
                }
            }
            foreach ($quedan as $clase => $minutos) {
                $parada->getTiempos()->add(new ParadaTiempo($parada, $clases[$clase], $minutos));
            }
        }
    }

    /** @return list<int> */
    private function salidasFuturas(int $rutaId): array
    {
        return array_map("intval", $this->em->getConnection()->fetchFirstColumn(
            "SELECT id FROM salida WHERE ruta_id = :r AND fecha > NOW() AND estado = :p",
            ["r" => $rutaId, "p" => EstadoSalida::PROGRAMADA->value],
        ));
    }

    private function ruta(int $id, ?LockMode $lock = null): Ruta
    {
        return $this->em->find(Ruta::class, $id, $lock)
            ?? throw new RutaRechazada("La ruta no existe.", "ruta_inexistente", 404);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $clase
     * @param list<int>       $ids
     *
     * @return array<int, T>
     */
    private function porId(string $clase, array $ids, string $siFalta): array
    {
        $ids = array_values(array_unique($ids));
        $porId = [];
        foreach ($ids === [] ? [] : $this->em->getRepository($clase)->findBy(["id" => $ids]) as $e) {
            $porId[(int) $e->getId()] = $e;
        }
        if (count($porId) !== count($ids)) {
            throw new RutaRechazada($siFalta, "referencia_inexistente");
        }

        return $porId;
    }
}
