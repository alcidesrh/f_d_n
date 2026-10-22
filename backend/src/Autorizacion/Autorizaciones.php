<?php

declare(strict_types=1);

namespace App\Autorizacion;

use App\Acceso\Administracion\BitacoraAcceso;
use App\Acceso\Autorizacion;
use App\Bitacora\TipoRegistro;
use App\Entity\Enum\EstadoAutorizacion;
use App\Entity\Enum\OperacionAutorizacion;
use App\Entity\Enum\OperacionBoleto;
use App\Entity\SolicitudAutorizacion;
use App\Entity\Usuario;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Pedir y resolver autorizaciones (ADR-040). Pedirla: quien quedó bloqueado,
 * con un motivo, para lo que de verdad lo bloquea (`SujetosAutorizacion`) y
 * sin otra abierta para lo mismo. Resolverla (aprobar o rechazar): quien
 * cumple `ReglasAutorizacion::rechazoParaResolver`. Retirarla: quien la
 * pidió. Cada paso queda en la bitácora de la autorización (y de sus
 * boletos) y, tras el commit, avisa por el chat (`AvisosAutorizacion`).
 * Usarla es cosa de `Pases`, dentro de la operación autorizada.
 */
final class Autorizaciones
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly SujetosAutorizacion $sujetos,
        private readonly Autorizacion $autorizacion,
        private readonly BitacoraAcceso $bitacora,
        private readonly EventDispatcherInterface $eventos,
        private readonly ClockInterface $reloj,
    ) {}

    /** @throws AutorizacionRechazada */
    public function solicitar(Usuario $usuario, Autorizable $a, string $motivo): SolicitudAutorizacion
    {
        ReglasAutorizacion::exigirAutorizable($a->capacidad, $a->dispensa);

        return $this->transaccion->ejecutar(function () use ($usuario, $a, $motivo) {
            $ahora = $this->reloj->now();
            foreach ($this->abiertasDe($usuario) as $s) {
                if (ReglasAutorizacion::seSuperponen($s, $a) && $s->estadoEn($ahora)->abierta()) {
                    throw new AutorizacionRechazada(
                        sprintf("Ya tiene la autorización #%d %s para eso.", $s->getId(), $s->estadoEn($ahora) === EstadoAutorizacion::APROBADA ? "aprobada: vuelva a intentarlo" : "pendiente"),
                        "ya_solicitada",
                        409,
                        ["autorizacion" => $s->getId()],
                    );
                }
            }
            $sujeto = $this->sujetos->de($usuario, $a);
            $s = new SolicitudAutorizacion(
                $usuario,
                $a->capacidad->value,
                $a->dispensa,
                $a->sujeto,
                $a->ids,
                $a->cantidad,
                $sujeto["ubicacion"],
                $sujeto["empresa"],
                $sujeto["estacion"],
                $sujeto["detalle"],
                $motivo,
                $ahora,
            );
            $this->em->persist($s);
            $this->em->flush();
            $this->anotar($s, OperacionAutorizacion::SOLICITADA, OperacionBoleto::AUTORIZACION_SOLICITADA, $usuario, ["motivo" => $s->getMotivo()]);
            $id = (int) $s->getId();
            $this->transaccion->despuesDeConfirmar(fn() => $this->eventos->dispatch(new AutorizacionSolicitada($id)));

            return $s;
        });
    }

    /** @throws AutorizacionRechazada */
    public function aprobar(int $id, Usuario $quien, ?string $nota): SolicitudAutorizacion
    {
        return $this->resolver($id, $quien, function (SolicitudAutorizacion $s) use ($quien, $nota) {
            $s->aprobar($quien, $nota, $this->reloj->now());
            $this->anotar($s, OperacionAutorizacion::APROBADA, OperacionBoleto::AUTORIZACION_APROBADA, $quien, ["nota" => $s->getRespuesta()]);
        });
    }

    /** @throws AutorizacionRechazada */
    public function rechazar(int $id, Usuario $quien, string $motivo): SolicitudAutorizacion
    {
        return $this->resolver($id, $quien, function (SolicitudAutorizacion $s) use ($quien, $motivo) {
            $s->rechazar($quien, $motivo, $this->reloj->now());
            $this->anotar($s, OperacionAutorizacion::RECHAZADA, OperacionBoleto::AUTORIZACION_RECHAZADA, $quien, ["motivo" => $s->getRespuesta()]);
        });
    }

    /** Solo quien la pidió, pendiente o aprobada sin usar. @throws AutorizacionRechazada */
    public function retirar(int $id, Usuario $quien): SolicitudAutorizacion
    {
        return $this->transaccion->ejecutar(function () use ($id, $quien) {
            $s = $this->bloqueada($id);
            if ($s->getSolicitante()->getId() !== $quien->getId()) {
                throw new AutorizacionRechazada("Solo quien la pidió la retira.", "permiso", 403);
            }
            $s->retirar($this->reloj->now());
            $this->em->flush();
            $this->bitacora->anotar(TipoRegistro::AUTORIZACION, $id, OperacionAutorizacion::RETIRADA, $quien);
            $this->transaccion->despuesDeConfirmar(fn() => $this->eventos->dispatch(new AutorizacionResuelta($id)));

            return $s;
        });
    }

    /** @param callable(SolicitudAutorizacion): void $cambio */
    private function resolver(int $id, Usuario $quien, callable $cambio): SolicitudAutorizacion
    {
        return $this->transaccion->ejecutar(function () use ($id, $quien, $cambio) {
            $s = $this->bloqueada($id);
            $rechazo = ReglasAutorizacion::rechazoParaResolver($this->autorizacion->de($quien), $quien->getId(), $s);
            if ($rechazo !== null) {
                throw new AutorizacionRechazada($rechazo, "permiso", 403);
            }
            $cambio($s);
            $this->em->flush();
            $this->transaccion->despuesDeConfirmar(fn() => $this->eventos->dispatch(new AutorizacionResuelta($id)));

            return $s;
        });
    }

    private function bloqueada(int $id): SolicitudAutorizacion
    {
        return $this->em->find(SolicitudAutorizacion::class, $id, LockMode::PESSIMISTIC_WRITE)
            ?? throw new AutorizacionRechazada("La autorización no existe.", "no_encontrado", 404);
    }

    /** @return list<SolicitudAutorizacion> las pendientes y aprobadas de `$usuario` */
    private function abiertasDe(Usuario $usuario): array
    {
        return $this->em->createQueryBuilder()
            ->select("s")
            ->from(SolicitudAutorizacion::class, "s")
            ->where("s.solicitante = :usuario AND s.estado IN (:estados)")
            ->setParameter("usuario", $usuario->getId())
            ->setParameter("estados", [EstadoAutorizacion::PENDIENTE->value, EstadoAutorizacion::APROBADA->value])
            ->getQuery()
            ->getResult();
    }

    /**
     * En la bitácora de la autorización y, si es sobre boletos, en la de cada
     * boleto (como el legado).
     *
     * @param array<string, mixed> $detalle
     */
    private function anotar(SolicitudAutorizacion $s, OperacionAutorizacion $operacion, OperacionBoleto $enBoleto, Usuario $quien, array $detalle): void
    {
        $id = (int) $s->getId();
        $this->bitacora->anotar(TipoRegistro::AUTORIZACION, $id, $operacion, $quien, $detalle);
        if ($s->getSujeto() !== SolicitudAutorizacion::SUJETO_BOLETOS) {
            return;
        }
        foreach ($s->getSujetoIds() as $boleto) {
            $this->bitacora->anotar(TipoRegistro::BOLETO, $boleto, $enBoleto, $quien, [
                "autorizacion" => $id,
                "operacion" => ReglasAutorizacion::operacion($s->getCapacidad()),
                "dispensa" => $s->getDispensa()->etiqueta(),
                ...$detalle,
            ]);
        }
    }
}
