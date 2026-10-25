<?php

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Entity\Enum\EstadoBoletoAsiento;
use App\Sincronizacion\Legado\ConexionLegado;
use App\Sincronizacion\Legado\ImportadorLegado;
use App\Sincronizacion\Nuevo\EscritorLegado;
use App\Sincronizacion\Nuevo\Traduccion;
use Doctrine\DBAL\Connection;

/**
 * Compara un día de los dos sistemas (ADR-042): boletos de las salidas de
 * ese día y encomiendas creadas ese día. Es la red de seguridad de la
 * sincronización: lo que se perdió por un fallo se ve aquí.
 *
 * Reparar hace solo lo que no puede equivocarse:
 * - trae del legado lo que falta aquí (la importación no duplica);
 * - lleva allá las encomiendas de aquí que no llegaron (por `sync_cambio`);
 * - anula allá los boletos `FDN:` huérfanos: los escribió una venta de aquí
 *   que no se confirmó (el proceso murió entre los dos commits).
 * Los estados distintos y los boletos de aquí que faltan allá solo se
 * informan: decidir cuál vale es de una persona.
 */
class Conciliador
{
    /** Un boleto `FDN:` sin pareja más viejo que esto es huérfano (la venta ya terminó o falló). */
    public const MINUTOS_HUERFANO = 10;

    public function __construct(
        private readonly Connection $db,
        private readonly ConexionLegado $legado,
        private readonly ImportadorLegado $importador,
        private readonly EscritorLegado $escritor,
    ) {}

    /**
     * @return array{
     *     boletosFaltanAqui: list<int>, boletosHuerfanosAlla: list<int>, boletosFaltanAlla: list<int>,
     *     estadosDistintos: list<array{legado: int, aqui: int, estadoLegado: int, estadoAqui: string}>,
     *     encomiendasFaltanAqui: list<int>, encomiendasFaltanAlla: list<int>
     * }
     */
    public function comparar(\DateTimeImmutable $dia): array
    {
        $desde = $dia->setTime(0, 0);
        $hasta = $desde->modify("+1 day");

        $alla = [];
        foreach ($this->legado->filas(
            "SELECT b.id, b.estado_id, b.identificador_web, CONVERT(varchar(19), b.fecha_creacion, 120) AS creado
               FROM boleto b JOIN salida s ON s.id = b.salida_id
              WHERE s.fecha >= :desde AND s.fecha < :hasta",
            ["desde" => $desde, "hasta" => $hasta],
        ) as $b) {
            $alla[(int) $b["id"]] = $b;
        }
        $aqui = [];
        $sinLegado = [];
        foreach ($this->db->fetchAllAssociative(
            "SELECT b.id, b.legacy_id, b.estado FROM boleto_asiento b JOIN salida s ON s.id = b.salida_id
              WHERE s.fecha >= :desde AND s.fecha < :hasta AND s.legacy_id IS NOT NULL",
            ["desde" => $desde->format("Y-m-d H:i:s"), "hasta" => $hasta->format("Y-m-d H:i:s")],
        ) as $b) {
            if ($b["legacy_id"] === null) {
                if (!in_array($b["estado"], EstadoBoletoAsiento::liberanAsiento(), true)) {
                    $sinLegado[] = (int) $b["id"];
                }
                continue;
            }
            $aqui[(int) $b["legacy_id"]] = $b;
        }

        $faltanAqui = [];
        $huerfanos = [];
        $estados = [];
        $limite = (new \DateTimeImmutable(sprintf("-%d minutes", self::MINUTOS_HUERFANO)))->format("Y-m-d H:i:s");
        foreach ($alla as $id => $b) {
            if (isset($aqui[$id])) {
                $estadoAqui = EstadoBoletoAsiento::from((string) $aqui[$id]["estado"]);
                if (Traduccion::estadoBoleto($estadoAqui) !== (int) $b["estado_id"]) {
                    $estados[] = ["legado" => $id, "aqui" => (int) $aqui[$id]["id"], "estadoLegado" => (int) $b["estado_id"], "estadoAqui" => $estadoAqui->value];
                }
                continue;
            }
            if (!str_starts_with((string) $b["identificador_web"], Traduccion::PREFIJO)) {
                $faltanAqui[] = $id;
            } elseif (Traduccion::ocupa((int) $b["estado_id"]) && (string) $b["creado"] < $limite) {
                $huerfanos[] = $id;
            }
        }

        $encomiendasAlla = array_map("intval", array_column($this->legado->filas(
            "SELECT id FROM encomienda WHERE fecha_creacion >= :desde AND fecha_creacion < :hasta
                AND (identificador_web IS NULL OR identificador_web NOT LIKE 'FDN:%')",
            ["desde" => $desde, "hasta" => $hasta],
        ), "id"));
        $encomiendasAqui = $this->db->fetchAllAssociative(
            "SELECT id, legacy_id FROM encomienda WHERE creada >= :desde AND creada < :hasta",
            ["desde" => $desde->format("Y-m-d H:i:s"), "hasta" => $hasta->format("Y-m-d H:i:s")],
        );
        $conocidas = array_map("intval", array_filter(array_column(
            $this->db->fetchAllAssociative("SELECT legacy_id FROM encomienda WHERE legacy_id IS NOT NULL AND legacy_id IN (" . (implode(",", array_map(static fn(int $i) => "'{$i}'", $encomiendasAlla)) ?: "NULL") . ")"),
            "legacy_id",
        )));

        return [
            "boletosFaltanAqui" => $faltanAqui,
            "boletosHuerfanosAlla" => $huerfanos,
            "boletosFaltanAlla" => $sinLegado,
            "estadosDistintos" => $estados,
            "encomiendasFaltanAqui" => array_values(array_diff($encomiendasAlla, $conocidas)),
            "encomiendasFaltanAlla" => array_map("intval", array_column(array_filter($encomiendasAqui, static fn(array $e) => $e["legacy_id"] === null), "id")),
        ];
    }

    /**
     * @param array<string, list<mixed>> $diferencias lo que devolvió `comparar`
     *
     * @return array<string, int> lo que se reparó
     */
    public function reparar(array $diferencias): array
    {
        $hecho = ["boletos_traidos" => 0, "huerfanos_anulados" => 0, "encomiendas_traidas" => 0, "encomiendas_encoladas" => 0];
        if ($diferencias["boletosFaltanAqui"] !== []) {
            $salidas = array_map("intval", array_column($this->legado->filas(
                "SELECT DISTINCT salida_id FROM boleto WHERE id IN (" . implode(",", array_map("intval", $diferencias["boletosFaltanAqui"])) . ")",
            ), "salida_id"));
            $this->importador->salidas($salidas);
            $hecho["boletos_traidos"] = count($diferencias["boletosFaltanAqui"]);
        }
        // En modo «solo traer» solo se trae: nada se anula ni se lleva al legado.
        foreach ($this->legado->soloTraer ? [] : $diferencias["boletosHuerfanosAlla"] as $id) {
            $this->escritor->cambiarEstado((string) $id, Traduccion::estadoBoleto(EstadoBoletoAsiento::ANULADO), null, "Venta no confirmada en FDN (conciliación).");
            $hecho["huerfanos_anulados"]++;
        }
        if ($diferencias["encomiendasFaltanAqui"] !== []) {
            $this->importador->encomiendas($diferencias["encomiendasFaltanAqui"]);
            $hecho["encomiendas_traidas"] = count($diferencias["encomiendasFaltanAqui"]);
        }
        foreach ($this->legado->soloTraer ? [] : $diferencias["encomiendasFaltanAlla"] as $id) {
            $this->db->executeStatement("INSERT INTO sync_cambio (entidad, registro_id) VALUES ('encomienda', :id)", ["id" => $id]);
            $hecho["encomiendas_encoladas"]++;
        }

        return $hecho;
    }
}
