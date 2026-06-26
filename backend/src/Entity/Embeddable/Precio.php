<?php

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;
use Money\Currency;
use Money\Money;

#[ORM\Embeddable]
class Precio {
  #[ORM\Column(type: 'integer')]
  private int $monto;

  #[ORM\Column(type: 'string', length: 3)]
  private string $moneda;

  public function asMoney(): Money {
    return new Money($this->monto, new Currency($this->moneda));
  }

  public function __construct(
    int $monto = 0,
    string $moneda = 'GTQ'
  ) {
    $this->monto = $monto;
    $this->moneda = $moneda;
  }

  public function toMoney(): Money {
    return new Money(
      $this->monto,
      new Currency($this->moneda)
    );
  }

  public static function fromMoney(Money $money): self {
    return new self(
      (int) $money->getMonto(),
      $money->getMoneda()->getCode()
    );
  }

  public function getMonto(): int {
    return $this->monto;
  }

  public function getMoneda(): string {
    return $this->moneda;
  }
}
