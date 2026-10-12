# ADR-034: Depósitos de agencia (registrar, acreditar o rechazar) y consulta de saldos

**Estado:** Aceptada

## Contexto

En el legado, el menú "Agencia" tiene tres pantallas: **Registrar Depósito** (agencia, fecha, número de boleta, importe, aplica bono, observación), **Buscar Depósito** (filtros por id, agencia, rango de fechas y boleta; Acreditar y Rechazar sobre el elegido) y **Consultar Saldos** (estado, saldo principal, bono, total y total depositado de una agencia). El depósito (`agencia_deposito`) nace *registrado* sin tocar el saldo; un supervisor lo *acredita* (suma el importe al saldo principal y, si aplica, el porcentaje de bonificación al bono) o lo *rechaza* con un motivo. La agencia registra solo para sí, no decide el bono, y no acredita.

El modelo nuevo ya tenía el saldo (`Agencia.saldo`, libro `AgenciaMovimiento`, `SaldoAgencia`, ADR-021), pero solo con un "depósito" que acreditaba al instante desde el formulario de la agencia: no había boleta como documento con estado, ni flujo de verificación, ni búsqueda.

## Decisión

- **`AgenciaDeposito`** (tabla `agencia_deposito`): agencia, fecha del depósito, importe en centavos y moneda (la de la agencia), número de boleta, `aplicaBono`, bono acreditado, observación, estado (`EstadoDepositoAgencia`: `registrado → acreditado | rechazado`, nada más cambia), motivo de rechazo, quién y cuándo lo creó y lo resolvió, `legacyId`.
- **`App\Venta\Agencia\DepositosAgencia`** es el flujo:
  - *registrar*: agencia propia si el usuario es de agencia (y entonces `aplicaBono` siempre, como el legado), fecha no futura, boleta obligatoria y **no repetida** en la misma agencia salvo que la anterior esté rechazada (el legado no lo impedía: así no se acredita dos veces la misma boleta).
  - *acreditar*: bloquea el depósito, exige que siga registrado y que la moneda coincida, y mueve el saldo **solo** por `SaldoAgencia::depositar` (depósito + bonificación, mismos movimientos y aviso por chat que antes). Un usuario de agencia nunca acredita.
  - *rechazar*: motivo obligatorio; evento `DepositoRechazado` → aviso del sistema por chat a los usuarios de la agencia.
  - El "Depósito" del formulario de la agencia (`POST /api/agencias/{id}/depositos`) ahora registra y acredita en un paso (`registrarAcreditado`): todo depósito queda como documento y aparece en la búsqueda.
- **Un solo saldo.** El legado lleva saldo principal y bono por separado y el vendedor elige de cuál descontar. El modelo nuevo mantiene el saldo único de ADR-021 (la bonificación suma a él). "Consultar saldos" muestra el saldo disponible y, aparte, lo depositado y lo bonificado acreditado.
- **API** `DepositoAgenciaController`, `/api/depositos-agencia` (prefijo propio para no chocar con `/api/agencias/{id}` de API Platform): `GET /opciones`, `GET ?desde&hasta&id&agencia&boleta&estado&pagina&porPagina` (por defecto ±1 mes por la fecha del depósito, máx. un año; `FiltroDepositos`), `POST` (registrar), `POST /{id}/acreditar`, `POST /{id}/rechazar { motivo }`, `GET /saldos`. Un usuario de agencia solo ve la suya.
- **Permisos**: `agencia.depositar` (registrar, buscar y consultar saldos; permiso "Depositos de Agencia") y `agencia.acreditar` (además acreditar y rechazar; "Acreditacion de Depositos de Agencia" y "Supervision Venta"). Se asignan a los roles del legado que tenían el menú: `ROLE_AGENCIA` el primero; `ROLE_SUPERVISOR_AGENCIA`, `ROLE_PROPIETARIO` y `ROLE_ADMINISTRATIVOS` el segundo (migración `Version20261012120000` y `MigradorIAM::PERMISOS_ROLES_LEGACY`).
- **Frontend** `features/agencia/`: `/agencias/depositos/registrar`, `/agencias/depositos` (filtros, barra Registrar / Acreditar / Rechazar, `DataGrid`, `?id=` o `?agencia=&estado=`) y `/agencias/saldos` (tarjetas por agencia, totales por moneda, movimientos). Reglas puras en `core/agencia/` (con tests). Menú "Agencia" en el sidebar izquierdo (`Version20261012130000`).
- **Migración del legado** (paso "agencia"): los depósitos con su estado (sin mover el saldo, que ya se migró; un depósito aún registrado se acredita en el modelo nuevo y entonces suma) y el bono del legado (`agencia_bonificacion`), que antes se perdía, como una bonificación en el libro, una vez por agencia.

## Consecuencias

- `SaldoAgencia::depositar` solo se llama desde `DepositosAgencia`.
- "Total depositado" suma los depósitos acreditados, también los migrados (como el legado).
- El bono del legado se toma con su valor en la corrida en que se agrega; si la agencia ya estaba migrada, entra en la siguiente corrida del paso "agencia".
- Pendiente de verificar contra el legado: `Mapeador::agencia` toma un porcentaje de bonificación ≤ 1 como fracción, pero el legado calcula `importe × porciento × 0.01` (porcentaje). Con los datos actuales (todas las agencias en 10) no hay diferencia.
