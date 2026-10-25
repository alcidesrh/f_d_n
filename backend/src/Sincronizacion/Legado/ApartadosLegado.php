<?php

declare(strict_types=1);

namespace App\Sincronizacion\Legado;

use App\Entity\Asiento;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\ReservaAsiento;
use App\Entity\Salida;
use App\Entity\Trayecto;
use App\Sincronizacion\Interruptor;
use App\Venta\Itinerarios;
use App\Venta\PublicadorOcupacion;
use App\Venta\ReglasVenta;
use App\Venta\Transaccion;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Lo que el legado vende, apartado aquí antes (ADR-042). El bundle del
 * legado llama a `apartar` antes de guardar un boleto: si aquí el asiento
 * está vendido o apartado, el legado no lo vende. Si está libre, queda un
 * `ReservaAsiento` (como el carrito de la página web) que estorba a las
 * ventas de aquí hasta que el boleto llega con la importación (`confirmar`)
 * o vence.
 *
 * Lo que aquí no puede venderse (la salida no está, el asiento no existe en
 * su bus, no hay trayecto) no choca con nada: se deja vender allá sin apartar.
 */
class ApartadosLegado
{
    /** Lo que tarda como mucho el legado entre apartar y guardar. */
    public const DURACION_MINUTOS = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly ReglasVenta $reglas,
        private readonly Itinerarios $itinerarios,
        private readonly ImportadorLegado $importador,
        private readonly Interruptor $interruptor,
        private readonly PublicadorOcupacion $publicador,
    ) {}

    /**
     * Aparta los asientos de una venta del legado: todos o ninguno.
     *
     * @param list<int> $numeros números de asiento
     *
     * @return array{token: ?string, apartados: list<int>, expira: ?string}
     *
     * @throws \App\Venta\Excepcion\AsientosNoDisponibles con los números ocupados aquí
     */
    public function apartar(int $salidaLegado, ?int $origen, ?int $destino, array $numeros): array
    {
        $nada = ["token" => null, "apartados" => [], "expira" => null];
        if (!$this->interruptor->activo() || $numeros === []) {
            return $nada;
        }
        if ($this->salidaId($salidaLegado) === null) {
            // Una salida nueva del legado: se trae ahora para poder comprobar.
            $this->importador->salidas([$salidaLegado]);
        }
        $salidaId = $this->salidaId($salidaLegado);
        if ($salidaId === null) {
            return $nada;
        }

        $token = Uuid::v4();
        $expira = new \DateTimeImmutable(sprintf("+%d minutes", self::DURACION_MINUTOS));
        $apartados = $this->transaccion->ejecutar(function () use ($salidaLegado, $salidaId, $origen, $destino, $numeros, $token, $expira) {
            // El mismo bloqueo que la venta de aquí: una u otra ve a la otra.
            $salida = $this->em->find(Salida::class, $salidaId, LockMode::PESSIMISTIC_WRITE);
            $itinerario = $this->itinerarios->deSalida($salida);
            $trayectoId = ($origen !== null && $destino !== null ? $itinerario->trayectoEntre($origen, $destino) : null) ?? $itinerario->trayectoId;
            if ($trayectoId === null || $salida->getBus() === null) {
                return [];
            }
            $tramo = ($origen !== null && $destino !== null ? $itinerario->tramoEntre($origen, $destino) : null) ?? $itinerario->completo();
            $asientos = $this->em->getRepository(Asiento::class)->findBy(["bus" => $salida->getBus(), "numero" => array_values(array_unique($numeros))]);
            if ($asientos === []) {
                return [];
            }
            $this->reglas->exigirDisponibles($salida, $tramo, $asientos);

            $trayecto = $this->em->getReference(Trayecto::class, $trayectoId);
            foreach ($asientos as $asiento) {
                $this->em->persist((new ReservaAsiento($token, $salida, $asiento, $trayecto, $expira))->ocuparTramo($tramo->desde, $tramo->hasta));
            }
            $this->em->getConnection()->insert("sync_apartado", [
                "token" => $token->toRfc4122(),
                "salida_id" => $salidaId,
                "detalle" => json_encode(["salida_legado" => $salidaLegado, "origen" => $origen, "destino" => $destino, "numeros" => $numeros], JSON_THROW_ON_ERROR),
            ]);

            return array_map(static fn(Asiento $a) => (int) $a->getNumero(), $asientos);
        });
        if ($apartados === []) {
            return $nada;
        }
        $this->publicador->cambio($salidaId);

        return ["token" => $token->toRfc4122(), "apartados" => $apartados, "expira" => $expira->format(\DATE_ATOM)];
    }

    /**
     * El legado guardó la venta: se trae ya su salida (con los boletos
     * nuevos) y el apartado sobra.
     *
     * @return array<string, int> lo que trajo la importación
     */
    public function confirmar(string $token): array
    {
        $apartado = $this->apartado($token);
        if ($apartado === null) {
            return [];
        }
        $detalle = json_decode((string) $apartado["detalle"], true, flags: JSON_THROW_ON_ERROR);
        $contadores = $this->importador->salidas([(int) $detalle["salida_legado"]]);
        $this->em->getConnection()->executeStatement("UPDATE sync_apartado SET confirmado_en = NOW() WHERE token = :t", ["t" => $token]);
        $this->quitarReservas($token, (int) $apartado["salida_id"]);

        return $contadores;
    }

    /** El legado no guardó la venta: el apartado se suelta ya. */
    public function liberar(string $token): void
    {
        $apartado = $this->apartado($token);
        if ($apartado === null) {
            return;
        }
        $this->em->getConnection()->executeStatement("DELETE FROM sync_apartado WHERE token = :t", ["t" => $token]);
        $this->quitarReservas($token, (int) $apartado["salida_id"]);
    }

    /**
     * Apartados cuyo boleto ya llegó (si no llegó a confirmarse: el asiento
     * estaba libre al apartar, así que el boleto que lo ocupa ahora es el
     * del legado) y los de más de un día (sus reservas ya vencieron).
     */
    public function limpiar(): int
    {
        $db = $this->em->getConnection();
        $db->executeStatement(
            "DELETE FROM reserva_asiento r USING sync_apartado a
              WHERE r.token = a.token AND EXISTS (
                    SELECT 1 FROM boleto_asiento b
                     WHERE b.salida_id = r.salida_id AND b.asiento_id = r.asiento_id
                       AND b.legacy_id IS NOT NULL AND b.estado NOT IN (:libres))",
            ["libres" => EstadoBoletoAsiento::liberanAsiento()],
            ["libres" => ArrayParameterType::STRING],
        );

        return $db->executeStatement("DELETE FROM sync_apartado WHERE creado_en < NOW() - INTERVAL '1 day'");
    }

    private function quitarReservas(string $token, int $salidaId): void
    {
        $this->em->getConnection()->executeStatement("DELETE FROM reserva_asiento WHERE token = :t", ["t" => $token]);
        $this->publicador->cambio($salidaId);
    }

    /** @return array<string, mixed>|null */
    private function apartado(string $token): ?array
    {
        if (!Uuid::isValid($token)) {
            return null;
        }

        return $this->em->getConnection()->fetchAssociative("SELECT * FROM sync_apartado WHERE token = :t", ["t" => $token]) ?: null;
    }

    private function salidaId(int $legacyId): ?int
    {
        $id = $this->em->getConnection()->fetchOne("SELECT id FROM salida WHERE legacy_id = :l", ["l" => (string) $legacyId]);

        return $id === false ? null : (int) $id;
    }
}
