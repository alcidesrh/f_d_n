<?php

namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use App\ApiResource\Agnostic;
use App\Useful\Doctrine;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

final class CollectionResolver implements QueryItemResolverInterface {

  public function __construct(private EntityManagerInterface $entityManagerInterface, private IriConverterInterface $iriConverter) {
  }
  /**
   */
  public function __invoke(?object $item, array $context): object {
    $metadata = new Agnostic();

    // if ($context['args']['resource'] == 'Status') {
    //   $metadata->data = \array_map(fn($item) => ['id' => $item, 'label' => $item], Status::cases());
    //   return $metadata;
    // } else {
    $metadata->data = (new ArrayCollection($this->entityManagerInterface->getRepository(Doctrine::entityNamespace($context['args']['resource']))->findAll()))->map(fn($v) => ['id' => $this->iriConverter->getIriFromResource($v), 'label' => $v->getLabel()])->toArray();
    // }
    return $metadata;
  }
}
