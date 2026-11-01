<?php

declare(strict_types=1);

namespace App\Correo;

use App\Chat\Directorio;
use App\Chat\Perfil;
use App\Chat\Push\AvisosPush;
use App\Correo\Envio\EnviarCorreo;
use App\Entity\CorreoCuenta;
use App\Entity\CorreoMensaje;
use App\Entity\Enum\CarpetaCorreo;
use App\Entity\Enum\EnvioCorreo;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Redactar como en Gmail: el borrador nace en cuanto se empieza a escribir
 * (o al pulsar Responder/Reenviar, ya con destinatarios, asunto y cita), se
 * guarda solo mientras se escribe y no se pierde hasta descartarlo. Enviar
 * lo pasa a Enviados (la misma fila), lo deja en el buzón de cada
 * destinatario de la casa en el acto y encola la entrega a los de fuera
 * (`EnviarCorreoHandler`).
 */
final class Borradores
{
    public const MAX_DESTINATARIOS = 50;
    public const MAX_TEXTO = 200_000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Cuentas $cuentas,
        private readonly Dominios $dominios,
        private readonly Buzon $buzon,
        private readonly Adjuntos $adjuntos,
        private readonly MessageBusInterface $bus,
        private readonly AvisosPush $push,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * Un borrador nuevo; con `$origen` y `$modo`, la respuesta o el reenvío
     * de ese correo ya armado.
     *
     * @param array<string, mixed> $datos lo que ya se escribió (`para`, `cc`, `cco`, `asunto`, `texto`)
     */
    public function crear(CorreoCuenta $cuenta, array $datos = [], ?CorreoMensaje $origen = null, ?string $modo = null): CorreoMensaje
    {
        $b = new CorreoMensaje($cuenta, CarpetaCorreo::Borradores, $this->cuentas->direccion($cuenta), $this->nombre($cuenta), $this->reloj->now());
        if ($origen !== null) {
            if ($origen->getCuenta() !== $cuenta || $origen->esBorrador()) {
                throw new CorreoRechazado("El correo no existe.", 404);
            }
            $modo = in_array($modo, Redaccion::MODOS, true) ? $modo : Redaccion::RESPONDER;
            $this->armar($b, $origen, $modo);
        }
        $this->em->persist($b);
        $this->aplicar($b, $datos);
        $this->em->flush();
        $this->buzon->avisar($cuenta);

        return $b;
    }

    /** Lo que se escribió hasta ahora (lo manda el navegador mientras se escribe). @param array<string, mixed> $datos */
    public function guardar(CorreoMensaje $b, array $datos): CorreoMensaje
    {
        $this->borrador($b);
        $this->aplicar($b, $datos);
        $b->setFecha($this->reloj->now());
        $this->em->flush();

        return $b;
    }

    public function descartar(CorreoMensaje $b): void
    {
        $this->borrador($b);
        $this->buzon->aplicar($b->getCuenta(), [(int) $b->getId()], "eliminar");
    }

    public function adjuntar(CorreoMensaje $b, UploadedFile $archivo): array
    {
        $this->borrador($b);
        $a = $this->adjuntos->subir($b, $archivo);
        $b->setFecha($this->reloj->now());
        $this->em->flush();

        return $this->adjuntos->presentar($a);
    }

    public function quitarAdjunto(CorreoMensaje $b, int $adjunto): void
    {
        $this->borrador($b);
        foreach ($b->getAdjuntos() as $a) {
            if ($a->getId() === $adjunto) {
                $ruta = $a->getRuta();
                $b->quitarAdjunto($a);
                $this->em->remove($a);
                $this->em->flush();
                $this->adjuntos->purgar([$ruta]);

                return;
            }
        }
        throw new CorreoRechazado("El adjunto no existe.", 404);
    }

    /**
     * Envía el borrador. Los destinatarios de la casa deben existir (un
     * usuario activo) y ser alguien con quien el autor puede hablar
     * (`Directorio`, como en el chat); a los de fuera solo escribe quien tiene
     * `correo.externo`.
     */
    public function enviar(CorreoMensaje $b, bool $puedeExterno): CorreoMensaje
    {
        $this->borrador($b);
        $todos = [...$b->getPara(), ...$b->getCc(), ...$b->getCco()];
        if ($todos === []) {
            throw new CorreoRechazado("Agregue al menos un destinatario.");
        }
        $yo = $b->getCuenta();
        $perfil = Perfil::de($yo->getUsuario());
        $internas = [];
        $externos = [];
        foreach ($todos as $d) {
            if (!$this->dominios->esDeLaCasa($d["direccion"])) {
                $externos[] = $d["direccion"];
                continue;
            }
            $cuenta = $this->cuentas->deDireccion($d["direccion"]) ?? throw new CorreoRechazado(sprintf("No existe la cuenta %s.", $d["direccion"]));
            if ($cuenta !== $yo && !Directorio::puedenConversar($perfil, Perfil::de($cuenta->getUsuario()))) {
                throw new CorreoRechazado(sprintf("No puede escribir a %s.", $d["direccion"]), 403);
            }
            $internas[(int) $cuenta->getId()] = $cuenta;
        }
        if ($externos !== [] && !$puedeExterno) {
            throw new CorreoRechazado(sprintf("No tiene permiso para escribir fuera de la empresa (%s).", implode(", ", array_slice($externos, 0, 3))), 403);
        }

        $ahora = $this->reloj->now();
        $b->setHtml(Redaccion::html($b->getTexto()));
        $b->setResponderA($this->dominios->responderA($yo->getLocal()));
        $b->enviar(sprintf("%s@%s", bin2hex(random_bytes(12)), $this->dominios->principal()), $externos !== [], $ahora);

        $entregas = [];
        foreach ($internas as $cuenta) {
            $copia = new CorreoMensaje($cuenta, CarpetaCorreo::Entrada, $b->getDeDireccion(), $b->getDeNombre(), $ahora);
            $copia->setMessageId($b->getMessageId());
            $copia->setHilo($b->getEnRespuestaA(), $b->getReferencias());
            $copia->setDestinatarios($b->getPara(), $b->getCc());
            $copia->setResponderA($b->getResponderA());
            $copia->setAsunto($b->getAsunto());
            $copia->setTexto($b->getTexto());
            $copia->setHtml($b->getHtml());
            foreach ($b->getAdjuntos() as $a) {
                $this->em->persist($a->copiarA($copia));
            }
            $this->em->persist($copia);
            $entregas[] = $copia;
        }
        $this->em->flush();

        foreach ($entregas as $copia) {
            $this->buzon->avisar($copia->getCuenta(), (int) $copia->getId(), ["de" => $copia->getDeNombre() ?? $copia->getDeDireccion(), "asunto" => $copia->getAsunto()]);
            $this->push->avisar([(int) $copia->getCuenta()->getUsuario()->getId()], self::notificacion($copia));
        }
        $this->buzon->avisar($yo);
        if ($b->getEnvio() === EnvioCorreo::Pendiente) {
            $this->bus->dispatch(new EnviarCorreo((int) $b->getId()));
        }

        return $b;
    }

    /** Vuelve a intentar la entrega de un enviado que falló. */
    public function reintentar(CorreoMensaje $m): void
    {
        if ($m->getEnvio() !== EnvioCorreo::Fallido) {
            throw new CorreoRechazado("Ese correo no tiene una entrega fallida.");
        }
        $m->enviar((string) $m->getMessageId(), true, $m->getFecha());
        $this->em->flush();
        $this->bus->dispatch(new EnviarCorreo((int) $m->getId()));
    }

    /** @return array{titulo: string, cuerpo: string, url: string, etiqueta: string} */
    public static function notificacion(CorreoMensaje $m): array
    {
        return [
            "titulo" => "Correo de " . ($m->getDeNombre() ?? $m->getDeDireccion()),
            "cuerpo" => $m->getAsunto() !== "" ? $m->getAsunto() : Redaccion::extracto($m->getTexto(), 100),
            "url" => sprintf("/correo/%d", $m->getId()),
            "etiqueta" => sprintf("correo-%d", $m->getId()),
        ];
    }

    private function armar(CorreoMensaje $b, CorreoMensaje $origen, string $modo): void
    {
        $de = ["direccion" => $origen->getDeDireccion(), "nombre" => $origen->getDeNombre()];
        $esEnviado = $origen->getCarpeta() === CarpetaCorreo::Enviados || $origen->getCarpetaPrevia() === CarpetaCorreo::Enviados;
        $d = Redaccion::destinatarios($modo, $de, $origen->getResponderA(), $origen->getPara(), $origen->getCc(), $this->cuentas->propias($b->getCuenta()), $esEnviado);
        $b->setDestinatarios($d["para"], $d["cc"]);
        $b->setAsunto(Redaccion::asunto($origen->getAsunto(), $modo));
        $quien = static fn(array $x) => $x["nombre"] !== null && $x["nombre"] !== "" ? sprintf("%s <%s>", $x["nombre"], $x["direccion"]) : $x["direccion"];
        $lista = static fn(array $ds) => implode(", ", array_map($quien, $ds));
        $b->setTexto(Redaccion::cita($modo, $quien($de), $origen->getFecha(), $origen->getAsunto(), $lista($origen->getPara()), $origen->getTexto()));
        $b->setOrigen($origen);
        if ($modo === Redaccion::REENVIAR) {
            foreach ($origen->getAdjuntos() as $a) {
                if (!$a->isEnLinea()) {
                    $this->em->persist($a->copiarA($b));
                }
            }
        } elseif ($origen->getMessageId() !== null) {
            $refs = trim(($origen->getReferencias() ?? "") . " " . $origen->getMessageId());
            $b->setHilo($origen->getMessageId(), implode(" ", array_slice(array_values(array_unique(explode(" ", $refs))), -20)));
        }
    }

    /** @param array<string, mixed> $datos */
    private function aplicar(CorreoMensaje $b, array $datos): void
    {
        $para = array_key_exists("para", $datos) ? Direcciones::deEntrada($datos["para"]) : $b->getPara();
        $cc = array_key_exists("cc", $datos) ? Direcciones::deEntrada($datos["cc"]) : $b->getCc();
        $cco = array_key_exists("cco", $datos) ? Direcciones::deEntrada($datos["cco"]) : $b->getCco();
        if (count($para) + count($cc) + count($cco) > self::MAX_DESTINATARIOS) {
            throw new CorreoRechazado(sprintf("Un correo puede llevar hasta %d destinatarios.", self::MAX_DESTINATARIOS));
        }
        $b->setDestinatarios($para, $cc, $cco);
        if (array_key_exists("asunto", $datos)) {
            $b->setAsunto((string) $datos["asunto"]);
        }
        if (array_key_exists("texto", $datos)) {
            $texto = (string) $datos["texto"];
            if (mb_strlen($texto) > self::MAX_TEXTO) {
                throw new CorreoRechazado("El texto es demasiado largo.");
            }
            $b->setTexto($texto);
        }
    }

    private function borrador(CorreoMensaje $b): void
    {
        if (!$b->esBorrador()) {
            throw new CorreoRechazado("Ese correo ya no es un borrador.", 409);
        }
    }

    private function nombre(CorreoCuenta $cuenta): string
    {
        $u = $cuenta->getUsuario();
        $nombre = trim(($u->getNombre() ?? "") . " " . ($u->getApellido() ?? ""));

        return $nombre !== "" ? $nombre : $u->getUsername();
    }
}
