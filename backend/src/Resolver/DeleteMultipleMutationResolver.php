<?php

namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\MutationResolverInterface;
use App\Acceso\Catalogo\CatalogoRecursos;
use App\DTO\DeleteMultipleDTO;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Borrado múltiple del listado genérico (`deleteAgnostic(ids, resource)`).
 * Solo entidades que se borran por la API genérica y solo los registros
 * que el usuario puede eliminar, cada uno en su empresa (ADR-039): si uno
 * no, no se borra ninguno.
 */
final class DeleteMultipleMutationResolver implements MutationResolverInterface
{
    public function __construct(
        private EntityManagerInterface $entityManagerInterface,
        private CatalogoRecursos $recursos,
        private AuthorizationCheckerInterface $auth,
    ) {}

    public function __invoke($item, array $context): ?object
    {
        if (isset(($args = $context['args'])['input']) && isset(($input = $args['input'])['ids']) && $ids = $input['ids']) {

            $class = !empty($input['resource']) ? 'App\\Entity\\' . $input['resource']  : $context['root_class'];
            $recurso = $this->recursos->de($class);
            if ($recurso === null || $recurso->eliminar === null || !$recurso->borrable || !$this->auth->isGranted($recurso->eliminar)) {
                throw new AccessDeniedException('No tiene permiso para eliminar estos registros.');
            }
            foreach ($this->entityManagerInterface->getRepository($class)->findBy(['id' => $ids]) as $registro) {
                if (!$this->auth->isGranted($recurso->eliminar, $registro)) {
                    throw new AccessDeniedException('Fuera de su alcance: algún registro es de una empresa donde no puede eliminar.');
                }
            }

            $criteria = new Criteria(Criteria::expr()->in('id', $ids));

            $this->entityManagerInterface->getRepository($class)->createQueryBuilder('a')
                ->addCriteria($criteria)->delete()->getQuery()->getResult();
        }
        return new DeleteMultipleDTO();
    }
}
