<?php

namespace App\DTO;

final class DeleteMultipleDTO {
  public $id;
  public function __construct(public bool $removed = true) {
  }
}
