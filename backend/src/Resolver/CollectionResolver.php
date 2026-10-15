<?php

namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use App\ApiResource\Agnostic;
use App\Services\BuscadorOpciones;
use App\Useful\Doctrine;
use Doctrine\ORM\EntityManagerInterface;

final class CollectionResolver implements QueryItemResolverInterface {

  public function __construct(
    private EntityManagerInterface $entityManagerInterface,
    private IriConverterInterface $iriConverter,
    private BuscadorOpciones $buscador,
  ) {
  }

  /**
   * Registros de `resource` como options `{ value: IRI, label }`.
   *
   * - Sin más argumentos: todos (catálogos chicos).
   * - `ids` (IRIs o ids): solo esos; da la etiqueta de lo ya elegido.
   * - `search` (aunque sea vacío): los primeros `limit` que coinciden
   *   (autocompletado de entidades con muchos registros, `BuscadorOpciones`).
   */
  public function __invoke(?object $item, array $context): object {
    $args = $context['args'];
    $class = Doctrine::entityNamespace($args['resource']);

    if (isset($args['ids'])) {
      $records = $this->buscador->porIds($class, array_map(
        fn($id) => is_string($id) && str_contains($id, '/') ? basename($id) : $id,
        $args['ids'],
      ));
    } elseif (isset($args['search'])) {
      $records = $this->buscador->buscar($class, $args['search'], $args['limit'] ?? BuscadorOpciones::LIMITE);
    } else {
      $records = $this->entityManagerInterface->getRepository($class)->findAll();
    }

    $metadata = new Agnostic();
    $metadata->data = array_map(
      fn($record) => ['value' => $this->iriConverter->getIriFromResource($record), 'label' => $record->getLabel()],
      $records,
    );
    return $metadata;
  }
}
