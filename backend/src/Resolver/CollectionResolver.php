<?php

namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use App\ApiResource\Agnostic;
use App\Acceso\Catalogo\CatalogoRecursos;
use App\Services\BuscadorOpciones;
use App\Useful\Doctrine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class CollectionResolver implements QueryItemResolverInterface {

  public function __construct(
    private EntityManagerInterface $entityManagerInterface,
    private IriConverterInterface $iriConverter,
    private BuscadorOpciones $buscador,
    private CatalogoRecursos $recursos,
    private AuthorizationCheckerInterface $auth,
  ) {
  }

  /**
   * Registros de `resource` como options `{ value: IRI, label }`.
   *
   * - Sin más argumentos: todos (catálogos chicos).
   * - `ids` (IRIs o ids): solo esos; da la etiqueta de lo ya elegido.
   * - `search` (aunque sea vacío): los primeros `limit` que coinciden
   *   (autocompletado de entidades con muchos registros, `BuscadorOpciones`).
   *
   * Con la lectura de su `#[Recurso]`; de una entidad con empresa, solo los
   * registros de las empresas donde la puede ver (ADR-039).
   */
  public function __invoke(?object $item, array $context): object {
    $args = $context['args'];
    $class = Doctrine::entityNamespace($args['resource']);
    $recurso = $this->recursos->de($class);
    if ($recurso === null || $recurso->oculto || (!$recurso->lecturaLibre && ($recurso->ver === null || !$this->auth->isGranted($recurso->ver)))) {
      throw new AccessDeniedException('No tiene permiso para ver estos registros.');
    }

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

    if ($recurso->ver !== null && $recurso->empresa !== null) {
      $records = array_values(array_filter($records, fn(object $r) => $this->auth->isGranted($recurso->ver, $r)));
    }

    $metadata = new Agnostic();
    $metadata->data = array_map(
      fn($record) => ['value' => $this->iriConverter->getIriFromResource($record), 'label' => $record->getLabel()],
      $records,
    );
    return $metadata;
  }
}
