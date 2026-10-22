<?php

declare(strict_types=1);

namespace App\Chat;

use App\Autorizacion\AutorizacionResuelta;
use App\Autorizacion\AutorizacionSolicitada;
use App\Autorizacion\AutorizacionUsada;
use App\Autorizacion\ConsultaAutorizaciones;
use App\Autorizacion\VistaAutorizacion;
use App\Entity\Enum\EstadoAutorizacion;
use App\Entity\Salida;
use App\Entity\SolicitudAutorizacion;
use App\Entity\Usuario;
use App\Salida\SalidasAnuladas;
use App\Venta\Agencia\DepositoRechazado;
use App\Venta\Agencia\SaldoAcreditado;
use App\Venta\Boleto\DatosBoleto;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Traduce hechos de otros módulos en avisos del sistema del chat (canal
 * "Avisos del sistema" de cada destinatario). Los eventos llegan después del
 * commit (`Transaccion::despuesDeConfirmar`): nunca se avisa algo revertido.
 * Un fallo aquí no rompe la operación que lo originó.
 */
final class AvisosDelSistema
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Conversaciones $chat,
        private readonly LoggerInterface $logger,
        private readonly ConsultaAutorizaciones $autorizaciones,
        private readonly AvisosChat $avisos,
    ) {}

    /**
     * A quienes la pueden resolver más cerca de la operación (ADR-040), con
     * la autorización como tarjeta; la bandeja de todos los que la pueden
     * resolver se refresca sola.
     */
    #[AsEventListener]
    public function autorizacionSolicitada(AutorizacionSolicitada $e): void
    {
        $this->seguro(function () use ($e) {
            $s = $this->em->find(SolicitudAutorizacion::class, $e->id);
            if ($s === null) {
                return;
            }
            // Por el chat, a los más cercanos a la operación; la bandeja de todos se refresca.
            $cercanos = $this->autorizaciones->resolvedoresCercanos($s);
            if ($cercanos !== []) {
                $this->chat->avisarSistema($cercanos, self::textoSolicitada($s), [["tipo" => "SolicitudAutorizacion", "id" => $e->id]]);
            }
            $resolvedores = $this->autorizaciones->resolvedores($s);
            $this->avisos->avisar([(int) $s->getSolicitante()->getId(), ...array_map(static fn(Usuario $u) => (int) $u->getId(), $resolvedores)], "autorizacion", ["id" => $e->id]);
        });
    }

    /** A quien la pidió: aprobada (ya puede repetir la operación) o rechazada (y por qué). */
    #[AsEventListener]
    public function autorizacionResuelta(AutorizacionResuelta $e): void
    {
        $this->seguro(function () use ($e) {
            $s = $this->em->find(SolicitudAutorizacion::class, $e->id);
            if ($s === null) {
                return;
            }
            $texto = self::textoResuelta($s);
            if ($texto !== null) {
                $this->chat->avisarSistema([$s->getSolicitante()], $texto, [["tipo" => "SolicitudAutorizacion", "id" => $e->id]]);
            }
            $this->avisos->avisar([(int) $s->getSolicitante()->getId(), ...array_map(static fn(Usuario $u) => (int) $u->getId(), $this->autorizaciones->resolvedores($s))], "autorizacion", ["id" => $e->id]);
        });
    }

    /** Sin mensaje: solo la campana de quien la pidió (un pase menos por usar). */
    #[AsEventListener]
    public function autorizacionUsada(AutorizacionUsada $e): void
    {
        $this->seguro(fn() => $this->avisos->avisar([$e->solicitante], "autorizacion", ["id" => $e->id]));
    }

    public static function textoSolicitada(SolicitudAutorizacion $s): string
    {
        return sprintf("%s pide autorización #%d. %s. Motivo: %s", $s->getSolicitante()->getFullName() ?: $s->getSolicitante()->getUsername(), $s->getId(), VistaAutorizacion::titulo($s), $s->getMotivo());
    }

    /** Null si no hay nada que contarle (la retiró él mismo). */
    public static function textoResuelta(SolicitudAutorizacion $s): ?string
    {
        $quien = $s->getResueltaPor()?->getFullName() ?: $s->getResueltaPor()?->getUsername() ?? "Alguien";

        return match ($s->getEstado()) {
            EstadoAutorizacion::APROBADA => sprintf("%s aprobó su autorización #%d (%s): ya puede repetir la operación; sirve una vez.%s", $quien, $s->getId(), VistaAutorizacion::titulo($s), $s->getRespuesta() ? " Nota: " . $s->getRespuesta() : ""),
            EstadoAutorizacion::RECHAZADA => sprintf("%s rechazó su autorización #%d (%s): %s", $quien, $s->getId(), VistaAutorizacion::titulo($s), $s->getRespuesta()),
            default => null,
        };
    }

    /** A los usuarios de la agencia: el WhatsApp de "ya le acreditamos". */
    #[AsEventListener]
    public function saldoAcreditado(SaldoAcreditado $e): void
    {
        $this->seguro(function () use ($e) {
            $usuarios = $this->em->getRepository(Usuario::class)->findBy(["agencia" => $e->agenciaId]);
            if ($usuarios !== []) {
                $this->chat->avisarSistema($usuarios, self::textoSaldo($e));
            }
        });
    }

    /** A los usuarios de la agencia: por qué no se acreditó su boleta. */
    #[AsEventListener]
    public function depositoRechazado(DepositoRechazado $e): void
    {
        $this->seguro(function () use ($e) {
            $usuarios = $this->em->getRepository(Usuario::class)->findBy(["agencia" => $e->agenciaId]);
            if ($usuarios !== []) {
                $this->chat->avisarSistema($usuarios, self::textoRechazo($e));
            }
        });
    }

    public static function textoRechazo(DepositoRechazado $e): string
    {
        return sprintf(
            "Se rechazó su depósito de %s (boleta %s): %s",
            DatosBoleto::importe(new Money($e->importe, new Currency($e->moneda)))["texto"],
            $e->numeroBoleta,
            $e->motivo,
        );
    }

    /** A la estación de origen de cada salida anulada, con la salida como tarjeta. */
    #[AsEventListener]
    public function salidasAnuladas(SalidasAnuladas $e): void
    {
        $this->seguro(function () use ($e) {
            /** @var list<Salida> $salidas */
            $salidas = $this->em->getRepository(Salida::class)->findBy(["id" => $e->ids], ["fecha" => "ASC"]);
            // Se avisa en cada estación donde se sube a la salida, no solo en su origen (ADR-030).
            $porOrigen = [];
            foreach ($salidas as $s) {
                $paradas = $s->getRuta()?->getParadas()->toArray() ?? [];
                array_pop($paradas);
                foreach ($paradas as $p) {
                    $porOrigen[(int) $p->getEnclave()->getId()][] = $s;
                }
            }
            foreach ($porOrigen as $origen => $grupo) {
                $usuarios = $this->em->getRepository(Usuario::class)->findBy(["estacion" => $origen]);
                if ($usuarios !== []) {
                    $this->chat->avisarSistema(
                        $usuarios,
                        self::textoAnuladas($grupo),
                        array_map(static fn(Salida $s) => ["tipo" => "Salida", "id" => (int) $s->getId()], array_slice($grupo, 0, 20)),
                    );
                }
            }
        });
    }

    public static function textoSaldo(SaldoAcreditado $e): string
    {
        $q = static fn(int $c) => DatosBoleto::importe(new Money(abs($c), new Currency($e->moneda)))["texto"];
        $saldo = sprintf("Saldo disponible: %s.", $q($e->saldo));
        if ($e->tipo === SaldoAcreditado::DEPOSITO) {
            return trim(sprintf(
                "Se acreditó su depósito de %s%s%s. %s",
                $q($e->importe),
                $e->referencia ? sprintf(" (boleta %s)", $e->referencia) : "",
                $e->bonificacion > 0 ? sprintf(" y una bonificación de %s", $q($e->bonificacion)) : "",
                $saldo,
            ));
        }

        return sprintf("Se ajustó su saldo en %s%s%s. %s", $e->importe < 0 ? "−" : "+", $q($e->importe), $e->observacion ? ": " . $e->observacion : "", $saldo);
    }

    /** @param list<Salida> $salidas que se suben en la misma estación, por fecha */
    public static function textoAnuladas(array $salidas): string
    {
        $fmt = new \IntlDateFormatter("es_GT", \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, "America/Guatemala", null, "EEE d 'de' MMM, h:mm a");
        $ruta = static fn(Salida $s) => sprintf("%s → %s", $s->getRuta()?->getOrigen()?->getNombre() ?? "?", $s->getRuta()?->getDestino()?->getNombre() ?? "?");
        $primera = $salidas[0];
        if (count($salidas) === 1) {
            // La hora en español ya termina en punto ("a. m."): sin duplicarlo.
            return rtrim(sprintf(
                "Se anuló la salida %s del %s%s",
                $ruta($primera),
                $primera->getFecha() ? $fmt->format($primera->getFecha()) : "?",
                $primera->getBus() ? sprintf(" (bus %s)", $primera->getBus()->getCodigo()) : "",
            ), ".") . ".";
        }

        return sprintf("Se anularon %d salidas desde %s:", count($salidas), $primera->getTrayecto()?->getOrigen()?->getNombre() ?? "su estación");
    }

    private function seguro(callable $aviso): void
    {
        try {
            $aviso();
        } catch (\Throwable $e) {
            $this->logger->error("No se pudo enviar el aviso del sistema: {error}", ["error" => $e->getMessage(), "exception" => $e]);
        }
    }
}
