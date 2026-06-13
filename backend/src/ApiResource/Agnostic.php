<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Query;
use App\Resolver\CollectionResolver;
use ApiPlatform\Metadata\ApiProperty;
use App\DTO\MetadataDTO;

#[ApiResource(
  graphQlOperations: [
    // new Mutation(
    //   name: 'delete',
    //   resolver: DeleteMultipleMutationResolver::class,
    //   args: ['ids' => ['type' => '[ID]'], 'resource' => ['type' => 'String']],
    //   read: false,
    //   write: false,
    //   output: DeleteMultipleDTO::class,
    // ),
    new Query(),
    new Query(
      name: 'collection',
      resolver: CollectionResolver::class,
      read: false,
      args: ['resource' => ['type' => 'String']],
      output: Agnostic::class,

    ),
    // new Query(
    //   name: 'crud',
    //   resolver: ItemResolver::class,
    //   read: false,
    //   args: ['entity' => ['type' => 'String'], 'form' => ['type' => 'Boolean!'], 'id' => ['type' => 'ID']],
    //   output: MetadataDTO::class,
    // ),

  ]
)]
class Agnostic {

  #[ApiProperty(identifier: true, writable: false)]
  public function getId(): string {
    return (new \DateTime())->format('Ymdms');
  }

  public function __construct(public array $data = []) {
    $id = new \DateTime();
  }

  public function getData(): array {
    return $this->data;
  }
}
