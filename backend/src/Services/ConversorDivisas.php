<?php

// src/Service/ConversorDivisas.php
namespace App\Services;

use Money\Currency;
use Money\Money;
use Money\Converter;
use Money\Exchange\FixedExchange;
use Money\Currencies\ISOCurrencies;

class ConversorDivisas {
  private float $tasaGtoToUsd;

  public function __construct() {
    // En producción, es mejor obtener este valor de la base de datos, 
    // una caché o una API externa (ej. Banco de Guatemala)
    $this->tasaGtoToUsd = 0.13; // Ejemplo: 1 GTQ = 0.13 USD
  }

  public function quetzalADolar(Money $precioEnQuetzales): Money {
    // Definimos la tasa de cambio inversa (cuántos GTQ es 1 USD)
    // Ejemplo: 1 / 0.13 = 7.6923
    $tasaInversa = 1 / $this->tasaGtoToUsd;

    $exchange = new FixedExchange([
      'USD' => [
        'GTQ' => $tasaInversa
      ]
    ]);

    $converter = new Converter(new ISOCurrencies(), $exchange);

    // Convertimos el dinero a USD (la librería maneja el redondeo automáticamente)
    return $converter->convert($precioEnQuetzales, new Currency('USD'));
  }
}
