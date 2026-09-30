<?php

namespace App\Services\Traits;

use App\Entity\Reservacion;

trait ReservaUtilTrait {

  /**
   * Chequear si el asiento está ocupado.
   *
   * @param array $asiento
   * @return void
   */
  public static function validarAsientoSistemaOcupado(array $asiento) {

    return $asiento['boleto'] || $asiento['reservacion'] || $asiento['boleto_pagina'];
  }

  public function siNoExisteCrearFactura(Reservacion $reservacion) {

    if (!$reservacion->getTransaccionId()) {
      return false;
    }
    if (!$reservacion->getFactura()?->getDte()) {
      return $this->emitirFactura($reservacion);
    }

    return true;
  }
}
