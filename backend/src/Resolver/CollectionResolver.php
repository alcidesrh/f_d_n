<?php

namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use App\ApiResource\Agnostic;
use App\Acceso\Catalogo\CatalogoRecursos;
use App\Entity\Empresa;
use App\Entity\Enclave;
use App\ApiResource\BuscadorOpciones;
use function Symfony\Component\String\u;
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
    $recurso = $this->recursos->de(u($args['resource'])->camel()->title()->toString());
    if ($recurso === null || $recurso->oculto || (!$recurso->lecturaLibre && ($recurso->ver === null || !$this->auth->isGranted($recurso->ver)))) {
      throw new AccessDeniedException('No tiene permiso para ver estos registros.');
    }
    $class = $recurso->clase;

    $filtros = $this->filtrosDeOpciones($class);
    if (isset($args['ids'])) {
      $records = $this->buscador->porIds($class, array_map(
        fn($id) => is_string($id) && str_contains($id, '/') ? basename($id) : $id,
        $args['ids'],
      ));
    } elseif (isset($args['search'])) {
      $records = $this->buscador->buscar($class, $args['search'], $args['limit'] ?? BuscadorOpciones::LIMITE, $filtros);
    } else {
      $records = $this->entityManagerInterface->getRepository($class)->findBy($filtros);
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

  /**
   * Qué registros cuentan como opción (filtros y selectores). Una empresa
   * desactivada o un enclave inactivo no aparecen; `ids` no filtra, porque es
   * la etiqueta de lo ya guardado.
   *
   * @return array<string, mixed>
   */
  private function filtrosDeOpciones(string $class): array {
    return match (true) {
      $class === Empresa::class => ['activa' => true],
      is_a($class, Enclave::class, true) => ['activo' => true],
      default => [],
    };
  }
}
