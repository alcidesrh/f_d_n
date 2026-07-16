<?php
// api/src/Resolver/BookMutationResolver.php
namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\MutationResolverInterface;
use App\DTO\DeleteMultipleDTO;
use App\Entity\User;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteMultipleMutationResolver implements MutationResolverInterface
{

    public function __construct(private EntityManagerInterface $entityManagerInterface) {}

    public function __invoke($item, array $context): ?object
    {

        if (isset(($args = $context['args'])['input']) && isset(($input = $args['input'])['ids']) && $ids = $input['ids']) {

            $class = !empty($input['resource']) ? 'App\\Entity\\' . $input['resource']  : $context['root_class'];

            $criteria = new Criteria(Criteria::expr()->in('id', $ids));

            $items = $this->entityManagerInterface->getRepository($class)->createQueryBuilder('a')
                ->addCriteria($criteria)->delete()->getQuery()->getResult();
            // $this->entityManagerInterface->flush();
        }
        return new DeleteMultipleDTO();
    }
}
