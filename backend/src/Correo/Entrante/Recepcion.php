<?php

declare(strict_types=1);

namespace App\Correo\Entrante;

use App\Chat\Push\AvisosPush;
use App\Correo\Adjuntos;
use App\Correo\Borradores;
use App\Correo\Buzon;
use App\Correo\Cuentas;
use App\Correo\Dominios;
use App\Correo\Redaccion;
use App\Entity\CorreoAdjunto;
use App\Entity\CorreoCuenta;
use App\Entity\CorreoMensaje;
use App\Entity\Enum\CarpetaCorreo;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Deja un correo que llegó de fuera en la Entrada de cada cuenta de la casa
 * a la que va. Si el proveedor lo reintenta, no se duplica (mismo
 * `Message-ID` en la misma cuenta). Lo que va a direcciones sin cuenta se
 * descarta (y se registra).
 */
final class Recepcion
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Cuentas $cuentas,
        private readonly Dominios $dominios,
        private readonly Adjuntos $adjuntos,
        private readonly Buzon $buzon,
        private readonly AvisosPush $push,
        private readonly ClockInterface $reloj,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return int en cuántos buzones quedó */
    public function recibir(CorreoEntrante $c): int
    {
        /** @var array<int, CorreoCuenta> $cuentas */
        $cuentas = [];
        foreach ($c->destinatarios() as $direccion) {
            $cuenta = $this->cuentas->deDireccion($direccion);
            if ($cuenta !== null) {
                $cuentas[(int) $cuenta->getId()] = $cuenta;
            } elseif ($this->dominios->esDeLaCasa($direccion)) {
                $this->logger->notice("Correo para {direccion}, que no tiene cuenta: se descarta.", ["direccion" => $direccion]);
            }
        }

        $ahora = $this->reloj->now();
        $texto = trim($c->texto) !== "" ? $c->texto : ($c->html !== null ? Redaccion::textoDeHtml($c->html) : "");
        /** @var list<CorreoAdjunto> $archivos los del primer buzón; los demás comparten el archivo */
        $archivos = [];
        $nuevos = [];
        foreach ($cuentas as $cuenta) {
            if ($c->messageId !== null && $this->yaEsta($cuenta, $c->messageId)) {
                continue;
            }
            $m = new CorreoMensaje($cuenta, CarpetaCorreo::Entrada, $c->de["direccion"], $c->de["nombre"], $ahora);
            $m->setMessageId($c->messageId);
            $m->setHilo($c->enRespuestaA, $c->referencias);
            $m->setDestinatarios($c->para, $c->cc);
            $m->setResponderA($c->responderA);
            $m->setAsunto($c->asunto);
            $m->setTexto($texto);
            $m->setHtml($c->html);
            $m->setExterno(!$this->dominios->esDeLaCasa($c->de["direccion"]));
            $this->em->persist($m);
            if ($archivos === []) {
                foreach ($c->adjuntos as $a) {
                    $guardado = $this->adjuntos->guardar($m, $a["contenido"], $a["nombre"], $a["tipo"], $a["contentId"], $a["enLinea"]);
                    if ($guardado !== null) {
                        $archivos[] = $guardado;
                    }
                }
            } else {
                foreach ($archivos as $a) {
                    $this->em->persist($a->copiarA($m));
                }
            }
            $nuevos[] = $m;
        }
        $this->em->flush();

        foreach ($nuevos as $m) {
            $this->buzon->avisar($m->getCuenta(), (int) $m->getId(), ["de" => $m->getDeNombre() ?? $m->getDeDireccion(), "asunto" => $m->getAsunto()]);
            $this->push->avisar([(int) $m->getCuenta()->getUsuario()->getId()], Borradores::notificacion($m));
        }

        return count($nuevos);
    }

    private function yaEsta(CorreoCuenta $cuenta, string $messageId): bool
    {
        return (bool) $this->em->getConnection()->fetchOne(
            "SELECT 1 FROM correo_mensaje WHERE cuenta_id = ? AND message_id = ? AND carpeta <> 'enviados' LIMIT 1",
            [$cuenta->getId(), $messageId],
        );
    }
}
