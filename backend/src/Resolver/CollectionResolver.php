<?php

namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use App\ApiResource\Agnostic;
use App\Useful\Doctrine;
use Doctrine\ORM\EntityManagerInterface;

final class CollectionResolver implements QueryItemResolverInterface {

  public function __construct(private EntityManagerInterface $entityManagerInterface, private IriConverterInterface $iriConverter) {
  }
  /** Todos los registros de `resource` como options `{ value: IRI, label }`. */
  public function __invoke(?object $item, array $context): object {
    $records = $this->entityManagerInterface->getRepository(Doctrine::entityNamespace($context['args']['resource']))->findAll();
    $metadata = new Agnostic();
    $metadata->data = array_map(
      fn($record) => ['value' => $this->iriConverter->getIriFromResource($record), 'label' => $record->getLabel()],
      $records,
    );
    return $metadata;
  }
}
