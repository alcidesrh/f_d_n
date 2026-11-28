# ADR-074: Cierre de salidas por la hora

**Estado:** Aceptada

## Contexto

El abordaje (ADR-032) cambia el estado de la salida, de sus boletos y de sus encomiendas solo cuando
alguien lo marca: abordar, iniciar, finalizar, desembarcar. En la práctica casi nadie lo hace. En la base
no había ninguna salida finalizada: las pasadas seguían programadas, abordando o iniciadas, con sus
boletos emitidos, chequeados o en tránsito y sus encomiendas a bordo. Los mensajes predeterminados ya se
habían rendido a eso (cuentan la hora programada, no el estado).

## Decisión

- **Tarea `cerrar_salidas`** del programador (ADR-044), cada 5 minutos (`App\Salida\Cierre\CierreSalidas`).
  Decide por la hora, no por lo que hizo el usuario. Lo que el usuario marca a tiempo sigue igual.
- **Finaliza** la salida abierta cuya llegada pasó hace una hora. La llegada es la hora de su última
  parada (`SalidaParada`), y sin horario, un día después de la salida.
  - Sus boletos vivos (emitido, chequeado, tránsito) de ventas confirmadas quedan **finalizados**: se da
    por hecho que viajaron. El no presentado (`cancelado`) del abordaje es una decisión de taquilla, no
    de la hora. Pasarlos a cancelado inflaría el ranking de cancelaciones del tablero con boletos que
    nadie chequeó.
  - Sus encomiendas a bordo (embarcada, tránsito) quedan **desembarcadas** en su destino si la salida pasa
    por él, y si no, en la última parada.
- **Inicia** la salida programada o abordando cuya hora de salida pasó hace 15 minutos y que aún no
  llega: chequeados → tránsito y embarcadas → tránsito, como `Abordaje::iniciar`. Los emitidos siguen
  chequeables hasta que se finaliza.
- **Por SQL**, una sentencia por paso, sin pasar por el workflow (ADR-037/038). No es exacto, porque no
  sabe si el bus salió tarde, y no tiene que serlo. El índice parcial `nat_salida_abierta_fecha` evita
  recorrer el historial.
- **Bitácora**: una fila por salida y por encomienda movida, sin usuario (`Sistema (por la hora)`,
  `detalle.automatico`). Los boletos no se anotan, por volumen.

## Fuera

- **Entregar** una encomienda desembarcada: no se deduce de la hora.
- **Depósitos de agencia** registrados (ADR-034): acreditar o rechazar es una decisión de alguien.
- Ya cierran solos: las autorizaciones vencidas (se calculan al leer, ADR-040), las afectaciones (centinela,
  ADR-070), las ventas pendientes y las reservas (`purgar_venta`) y las facturas en contingencia
  (`certificar_pendientes`).

## Consecuencias

- La sincronización (ADR-042) no lleva estos cambios al legado, porque no pasan por el flush. Si el legado
  vuelve a tocar una salida o un boleto, lo trae, y la siguiente corrida lo vuelve a cerrar. La
  conciliación puede mostrar `estadosDistintos` (chequeado allá, finalizado aquí).
- Un usuario que quiera iniciar o finalizar después de la hora encuentra la salida ya en ese estado.
- La primera corrida cierra todo el atraso de una vez. No avisa por Mercure si cambian más de 100 salidas.
