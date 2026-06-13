<?php

namespace App\DTO;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use Symfony\Component\Serializer\Attribute\Ignore;

final class CollectionDTO {

  public function __construct(public string $name = '') {
  }
  #[Ignore]
  public function getCurrentPage(): float {
    return 0;
  }

  #[ApiProperty(identifier: true)]
  public function getName(): string {
    return $this->name;
  }

  /**
   * Gets the number of items by page.
   */
  #[Ignore]
  public function getItemsPerPage(): float {
    return 0;
  }
}
