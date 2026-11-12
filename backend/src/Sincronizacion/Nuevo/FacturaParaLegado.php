<?php

declare(strict_types=1);

namespace App\Sincronizacion\Nuevo;

/**
 * Lo que falta escribir en el legado para que la factura de una venta de
 * aquí quede como él deja la suya (ADR-042). Allá cada boleto tiene su
 * `factura_generada` y los reportes y la contabilidad suman sus
 * `importeTotal`; una venta de varios boletos se arma con su artilugio:
 *
 * - cada boleto que la factura cubre (aquí no anulado ni reasignado) lleva
 *   su fila con el UUID, la serie y el número del DTE;
 * - el total del DTE va en la fila del cubierto de menor id (el portador) y
 *   las demás filas de ese DTE, en 0;
 * - `boletos_ticket` los asocia (no hay columna en `boleto`: el contrato
 *   está en el código del legado): `identificador_ticket` es el portador y
 *   `boletos`, los ids de los cubiertos, incluido él.
 *
 * Las filas de un DTE anulado aquí se anulan allá: la anulación total, y la
 * parcial de una factura conjunta, que aquí certifica otra para los que
 * quedan. Puro: recibe lo que hay en los dos lados y devuelve lo que falta;
 * con lo ya escrito, no queda nada.
 */
final readonly class FacturaParaLegado
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /**
     * @param array<int, string> $anular   fila de `factura_generada` → cuándo se anuló aquí
     * @param array<int, int>    $crear    boleto del legado sin fila de la factura vigente → su importe (centavos)
     * @param array<int, int>    $importes fila de la factura vigente con otro importe → el que le toca (centavos)
     * @param ?array{id: ?int, identificador: int, boletos: list<int>} $ticket el ticket por crear (`id` null) o por poner al día
     */
    public function __construct(
        public array $anular,
        public array $crear,
        public array $importes,
        public ?array $ticket,
    ) {}

    /**
     * @param list<int>                                                                 $cubiertos boletos del legado que cubre la factura vigente
     * @param array<int, array{fg: ?int, uuid: ?string, importe: int, anulada: bool}> $alla      cada boleto de la venta en el legado y su fila
     * @param ?array{uuid: string, total: int}                                         $vigente   la factura de la venta, si tiene una sin anular
     * @param array<string, string>                                                    $anuladas  UUID (`uuid()`) anulado aquí → cuándo
     * @param ?array{id: int, identificador: int, boletos: list<int>}                  $ticket    el de la venta, si ya existe
     */
    public static function calcular(array $cubiertos, array $alla, ?array $vigente, array $anuladas, ?array $ticket): self
    {
        $anular = [];
        foreach ($alla as $b) {
            if ($b["fg"] !== null && !$b["anulada"] && $b["uuid"] !== null && isset($anuladas[$b["uuid"]])) {
                $anular[$b["fg"]] = $anuladas[$b["uuid"]];
            }
        }

        $uuid = $vigente === null ? null : self::uuid($vigente["uuid"]);
        $cubiertos = array_values(array_unique(array_filter($cubiertos, static fn(int $id) => isset($alla[$id]))));
        sort($cubiertos);
        if ($uuid === null || isset($anuladas[$uuid]) || $cubiertos === []) {
            return new self($anular, [], [], null);
        }

        $portador = $cubiertos[0];
        $importe = static fn(int $boleto): int => $boleto === $portador ? $vigente["total"] : 0;
        $crear = [];
        foreach ($cubiertos as $id) {
            if ($alla[$id]["fg"] === null || $alla[$id]["uuid"] !== $uuid) {
                $crear[$id] = $importe($id);
            }
        }
        // Las filas que ya son de este DTE, también las de un boleto reasignado (su total pasa al portador).
        $importes = [];
        foreach ($alla as $id => $b) {
            if ($b["fg"] !== null && $b["uuid"] === $uuid && !isset($crear[$id]) && $b["importe"] !== $importe($id)) {
                $importes[$b["fg"]] = $importe($id);
            }
        }

        $nuevo = null;
        if ($ticket !== null) {
            $tenia = $ticket["boletos"];
            sort($tenia);
            if ($ticket["identificador"] !== $portador || $tenia !== $cubiertos) {
                $nuevo = ["id" => $ticket["id"], "identificador" => $portador, "boletos" => $cubiertos];
            }
        } elseif (count($cubiertos) > 1) {
            $nuevo = ["id" => null, "identificador" => $portador, "boletos" => $cubiertos];
        }

        return new self($anular, $crear, $importes, $nuevo);
    }

    public function vacio(): bool
    {
        return $this->anular === [] && $this->crear === [] && $this->importes === [] && $this->ticket === null;
    }

    /** El UUID de un DTE para comparar (el legado lo guarda en mayúsculas); null si no parece uno. */
    public static function uuid(mixed $valor): ?string
    {
        $uuid = strtolower(trim((string) $valor));

        return preg_match(self::UUID, $uuid) === 1 ? $uuid : null;
    }

    /**
     * `boletos_ticket.boletos`: el tipo `array` de Doctrine del legado
     * (`serialize`), con los ids como los da su `bigint` (texto).
     *
     * @param list<int> $boletos
     */
    public static function serializarBoletos(array $boletos): string
    {
        return serialize(array_map("strval", $boletos));
    }

    /** @return list<int> los ids de `boletos_ticket.boletos` (vacío si no se entiende) */
    public static function boletosDeTicket(?string $valor): array
    {
        $boletos = @unserialize((string) $valor, ["allowed_classes" => false]);

        return is_array($boletos) ? array_values(array_map("intval", array_filter($boletos, "is_numeric"))) : [];
    }
}
