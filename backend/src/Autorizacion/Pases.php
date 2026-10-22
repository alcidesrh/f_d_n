<?php

declare(strict_types=1);

namespace App\Autorizacion;

use App\Acceso\Administracion\BitacoraAcceso;
use App\Bitacora\TipoRegistro;
use App\Entity\Enum\EstadoAutorizacion;
use App\Entity\Enum\OperacionAutorizacion;
use App\Entity\SolicitudAutorizacion;
use App\Entity\Usuario;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * El lado de los servicios que operan (ADR-040): cuando una regla bloquea
 * una operación que admite autorización, buscan aquí un pase aprobado de
 * quien opera que la cubra. Si lo hay, lo usan dentro de su transacción (si
 * la operación se revierte, el pase sigue aprobado); si no, el bloqueo sale
 * con `autorizable` —lo necesario para pedirla— y, si ya hay una solicitud
 * abierta para eso, su número.
 */
final class Pases
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $reloj,
        private readonly BitacoraAcceso $bitacora,
        private readonly Transaccion $transaccion,
        private readonly EventDispatcherInterface $eventos,
    ) {}

    /** El pase aprobado y vigente de `$usuario` que cubre `$a`, o null. */
    public function vigente(Usuario $usuario, Autorizable $a): ?SolicitudAutorizacion
    {
        $ahora = $this->reloj->now();
        foreach ($this->abiertas($usuario, $a, EstadoAutorizacion::APROBADA) as $s) {
            if (ReglasAutorizacion::cubre($s, (int) $usuario->getId(), $a, $ahora)) {
                return $s;
            }
        }

        return null;
    }

    /**
     * El pase que cubre `$a` o el bloqueo con lo necesario para pedirlo.
     *
     * @throws VentaRechazada
     */
    public function exigir(Usuario $usuario, Autorizable $a, string $mensaje, string $codigo = "requiere_autorizacion", int $estadoHttp = 403): SolicitudAutorizacion
    {
        return $this->vigente($usuario, $a) ?? throw $this->bloqueo($usuario, $a, $mensaje, $codigo, $estadoHttp);
    }

    /**
     * Usa el pase: dentro de la transacción de la operación, con la fila
     * bloqueada (dos operaciones a la vez no gastan el mismo).
     *
     * @throws VentaRechazada si ya no está vigente
     */
    public function usar(SolicitudAutorizacion $s, Usuario $usuario): void
    {
        $this->em->lock($s, LockMode::PESSIMISTIC_WRITE);
        $this->em->refresh($s);
        try {
            $s->usar($this->reloj->now());
        } catch (AutorizacionRechazada $e) {
            throw new VentaRechazada(sprintf("La autorización #%d ya no está vigente: pida otra.", $s->getId()), "autorizacion_cerrada", 409);
        }
        $this->em->flush();
        $this->bitacora->anotar(TipoRegistro::AUTORIZACION, (int) $s->getId(), OperacionAutorizacion::USADA, $usuario);
        $evento = new AutorizacionUsada((int) $s->getId(), (int) $s->getSolicitante()->getId());
        $this->transaccion->despuesDeConfirmar(fn() => $this->eventos->dispatch($evento));
    }

    /** El rechazo de la operación, con lo necesario para pedir la autorización. */
    public function bloqueo(Usuario $usuario, Autorizable $a, string $mensaje, string $codigo = "requiere_autorizacion", int $estadoHttp = 403): VentaRechazada
    {
        $pendiente = null;
        foreach ($this->abiertas($usuario, $a, EstadoAutorizacion::PENDIENTE) as $s) {
            if (ReglasAutorizacion::seSuperponen($s, $a) && $s->estadoEn($this->reloj->now()) === EstadoAutorizacion::PENDIENTE) {
                $pendiente = $s->getId();
                break;
            }
        }

        return new VentaRechazada($mensaje, $codigo, $estadoHttp, ["autorizable" => [...$a->toArray(), "pendiente" => $pendiente]]);
    }

    /** @return list<SolicitudAutorizacion> */
    private function abiertas(Usuario $usuario, Autorizable $a, EstadoAutorizacion $estado): array
    {
        if ($usuario->getId() === null) {
            return [];
        }

        return $this->em->createQueryBuilder()
            ->select("s")
            ->from(SolicitudAutorizacion::class, "s")
            ->where("s.solicitante = :usuario AND s.capacidad = :capacidad AND s.dispensa = :dispensa AND s.sujeto = :sujeto AND s.estado = :estado AND s.venceEn > :ahora")
            ->setParameter("usuario", $usuario->getId())
            ->setParameter("capacidad", $a->capacidad->value)
            ->setParameter("dispensa", $a->dispensa->value)
            ->setParameter("sujeto", $a->sujeto)
            ->setParameter("estado", $estado->value)
            ->setParameter("ahora", $this->reloj->now())
            ->orderBy("s.id", "ASC")
            ->getQuery()
            ->getResult();
    }
}
