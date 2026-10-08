<?php

declare(strict_types=1);

namespace App\Chat;

use App\Entity\Salida;
use App\Entity\Usuario;
use App\Salida\SalidasAnuladas;
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
    ) {}

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

    /** A la estación de origen de cada salida anulada, con la salida como tarjeta. */
    #[AsEventListener]
    public function salidasAnuladas(SalidasAnuladas $e): void
    {
        $this->seguro(function () use ($e) {
            /** @var list<Salida> $salidas */
            $salidas = $this->em->getRepository(Salida::class)->findBy(["id" => $e->ids], ["fecha" => "ASC"]);
            $porOrigen = [];
            foreach ($salidas as $s) {
                $origen = $s->getTrayecto()?->getOrigen()?->getId();
                if ($origen !== null) {
                    $porOrigen[$origen][] = $s;
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

    /** @param list<Salida> $salidas mismo origen, por fecha */
    public static function textoAnuladas(array $salidas): string
    {
        $fmt = new \IntlDateFormatter("es_GT", \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, "America/Guatemala", null, "EEE d 'de' MMM, h:mm a");
        $ruta = static fn(Salida $s) => sprintf("%s → %s", $s->getTrayecto()?->getOrigen()?->getNombre() ?? "?", $s->getTrayecto()?->getDestino()?->getNombre() ?? "?");
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
