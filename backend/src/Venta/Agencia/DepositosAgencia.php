<?php

declare(strict_types=1);

namespace App\Venta\Agencia;

use App\Entity\Agencia;
use App\Entity\AgenciaDeposito;
use App\Entity\Enum\EstadoDepositoAgencia;
use App\Entity\Enum\TipoMovimientoAgencia;
use App\Entity\Usuario;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Depósitos de agencia (ADR-034): registrar la boleta, acreditarla (el
 * saldo se mueve solo aquí, por `SaldoAgencia`) o rechazarla. Un usuario
 * de agencia solo registra para la suya y no decide la bonificación
 * (como en el legado, siempre se pide). Cada operación abre su transacción.
 */
final class DepositosAgencia
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SaldoAgencia $saldo,
        private readonly Transaccion $transaccion,
        private readonly EventDispatcherInterface $eventos,
        private readonly ClockInterface $reloj,
    ) {}

    public function registrar(
        int $agenciaId,
        \DateTimeImmutable $fecha,
        int $importe,
        string $numeroBoleta,
        bool $aplicaBono,
        ?string $observacion,
        Usuario $usuario,
    ): AgenciaDeposito {
        return $this->transaccion->ejecutar(function () use ($agenciaId, $fecha, $importe, $numeroBoleta, $aplicaBono, $observacion, $usuario) {
            $agencia = $this->em->find(Agencia::class, $agenciaId) ?? throw new VentaRechazada("La agencia no existe.", "agencia_inexistente", 404);
            $propia = $usuario->getAgencia()?->getId();
            if ($propia !== null && $propia !== $agencia->getId()) {
                throw new VentaRechazada("Solo puede registrar depósitos de su agencia.", "agencia_ajena", 403);
            }
            $boleta = self::boleta($numeroBoleta);
            $hoy = $this->reloj->now();
            if ($fecha->setTime(0, 0) > $hoy->setTime(0, 0)) {
                throw new VentaRechazada("La fecha del depósito no puede ser futura.", "fecha_invalida");
            }
            if ($this->boletaVigente($agencia, $boleta)) {
                throw new VentaRechazada(sprintf("La boleta %s ya está registrada para esta agencia.", $boleta), "boleta_duplicada", 409);
            }

            $deposito = new AgenciaDeposito(
                $agencia,
                $fecha,
                $importe,
                $boleta,
                $propia !== null ? true : $aplicaBono,
                self::texto($observacion, AgenciaDeposito::MAX_OBSERVACION),
                $this->em->find(Usuario::class, $usuario->getId()),
                $hoy,
            );
            $this->em->persist($deposito);

            return $deposito;
        });
    }

    /**
     * Registra y acredita en un paso (el "Depósito" del formulario de la
     * agencia, para quien ya tiene la boleta verificada).
     */
    public function registrarAcreditado(
        int $agenciaId,
        \DateTimeImmutable $fecha,
        int $importe,
        string $numeroBoleta,
        bool $aplicaBono,
        ?string $observacion,
        Usuario $usuario,
    ): AgenciaDeposito {
        return $this->transaccion->ejecutar(function () use ($agenciaId, $fecha, $importe, $numeroBoleta, $aplicaBono, $observacion, $usuario) {
            $deposito = $this->registrar($agenciaId, $fecha, $importe, $numeroBoleta, $aplicaBono, $observacion, $usuario);
            $this->em->flush();

            return $this->acreditar((int) $deposito->getId(), $usuario);
        });
    }

    /** Pasa el depósito a acreditado y suma importe (y bonificación) al saldo. */
    public function acreditar(int $id, Usuario $usuario): AgenciaDeposito
    {
        return $this->transaccion->ejecutar(function () use ($id, $usuario) {
            $deposito = $this->bloquear($id);
            $agencia = $deposito->getAgencia();
            if ($agencia->getMoneda() !== $deposito->getMoneda()) {
                throw new VentaRechazada(sprintf(
                    "La agencia opera en %s y el depósito está en %s.",
                    $agencia->getMoneda(),
                    $deposito->getMoneda(),
                ), "moneda_distinta");
            }
            $deposito->exigirRegistrado("acreditar");
            if ($usuario->getAgencia() !== null) {
                throw new VentaRechazada("Una agencia no acredita sus propios depósitos.", "agencia_ajena", 403);
            }
            $quien = $this->em->find(Usuario::class, $usuario->getId());
            $movimientos = $this->saldo->depositar(
                $agencia,
                $deposito->getImporte(),
                $quien,
                $deposito->getNumeroBoleta(),
                $deposito->getObservacion() !== null ? mb_substr($deposito->getObservacion(), 0, 255) : null,
                $deposito->isAplicaBono(),
            );
            $bono = 0;
            foreach ($movimientos as $m) {
                if ($m->getTipo() === TipoMovimientoAgencia::BONIFICACION) {
                    $bono += $m->getMonto();
                }
            }
            $deposito->acreditar($bono, $quien, $this->reloj->now());

            return $deposito;
        });
    }

    public function rechazar(int $id, string $motivo, Usuario $usuario): AgenciaDeposito
    {
        return $this->transaccion->ejecutar(function () use ($id, $motivo, $usuario) {
            $deposito = $this->bloquear($id);
            $deposito->rechazar($motivo, $this->em->find(Usuario::class, $usuario->getId()), $this->reloj->now());
            $evento = new DepositoRechazado(
                (int) $deposito->getAgencia()->getId(),
                $deposito->getNumeroBoleta(),
                $deposito->getImporte(),
                $deposito->getMoneda(),
                (string) $deposito->getMotivoRechazo(),
            );
            $this->transaccion->despuesDeConfirmar(fn() => $this->eventos->dispatch($evento));

            return $deposito;
        });
    }

    /** Bonificación que tendría el depósito si se acreditara hoy. */
    public static function bonoPrevisto(AgenciaDeposito $d): int
    {
        if ($d->getEstado() !== EstadoDepositoAgencia::REGISTRADO) {
            return $d->getBono();
        }

        return $d->isAplicaBono() ? SaldoAgencia::bonificacion($d->getImporte(), $d->getAgencia()->getPorcentajeBonificacion()) : 0;
    }

    /** Número de boleta normalizado; vacío o muy largo se rechaza. */
    public static function boleta(string $numero): string
    {
        $numero = trim($numero);
        if ($numero === "") {
            throw new VentaRechazada("Indique el número de boleta.", "boleta_requerida");
        }
        if (mb_strlen($numero) > AgenciaDeposito::MAX_BOLETA) {
            throw new VentaRechazada(sprintf("El número de boleta no puede pasar de %d caracteres.", AgenciaDeposito::MAX_BOLETA), "boleta_invalida");
        }

        return $numero;
    }

    private function boletaVigente(Agencia $agencia, string $boleta): bool
    {
        return (bool) $this->em->createQueryBuilder()
            ->select("d.id")
            ->from(AgenciaDeposito::class, "d")
            ->where("d.agencia = :agencia AND d.numeroBoleta = :boleta AND d.estado <> :rechazado")
            ->setParameter("agencia", $agencia)
            ->setParameter("boleta", $boleta)
            ->setParameter("rechazado", EstadoDepositoAgencia::RECHAZADO)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function bloquear(int $id): AgenciaDeposito
    {
        return $this->em->find(AgenciaDeposito::class, $id, LockMode::PESSIMISTIC_WRITE)
            ?? throw new VentaRechazada("El depósito no existe.", "deposito_inexistente", 404);
    }

    private static function texto(?string $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v !== "" ? mb_substr($v, 0, $max) : null;
    }
}
