<?php

namespace App\DTO;

final class MetadataDTO extends DTOBase {


  public function __construct(public array $data = []) {
    $this->id = new \DateTime();
  }

  // public function getStatus(): array|null {
  //   return Status::cases(); // $this->status;
  // }

  public function getData(): array {
    return $this->data;
  }
}
