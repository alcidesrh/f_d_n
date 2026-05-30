<?php

namespace App\Entity\Base\Interfaces;

interface StatusInterface {
  const ESTADO_CREADO = 1;
  const ESTADO_PROCESANDO = 2;
  const ESTADO_FINALIZADO = 3;
  const ESTADO_ACTIVO = 4;
  const ESTADO_INACTIVO = -1;
  const ESTADO_CANCELADO = -2;
  const ESTADO_ERROR = -3;
}
