<?php

namespace App\EventListener;

use App\Acceso\Autorizacion;
use App\Doctrine\TenantFilter;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Habilita/deshabilita App\Doctrine\TenantFilter en cada request con las
 * empresas de las asignaciones vigentes del usuario (ADR-039): apagado sin
 * sesión o con alcance sobre todas las empresas.
 *
 * Se hace explícitamente en cada petición (no solo "si hace falta habilitarlo")
 * porque FrankenPHP en worker mode mantiene el kernel — y con él el
 * EntityManager y el estado de sus filtros Doctrine — vivo entre peticiones.
 * Sin este reset explícito, el filtro habilitado por un usuario podría filtrar
 * (o no filtrar) las consultas de la siguiente petición atendida por el mismo worker.
 */
final class TenantFilterListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly Autorizacion $autorizacion,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => "onKernelRequest"];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled("tenant_filter")) {
            $filters->disable("tenant_filter");
        }
        $user = $this->security->getUser();
        if (!$user instanceof Usuario) {
            return;
        }

        $empresas = $this->autorizacion->de($user)->general()->empresas();
        if ($empresas !== null) {
            $filters
                ->enable("tenant_filter")
                ->setParameterList(TenantFilter::PARAMETRO, $empresas, "integer");
        }
    }
}
