# ADR-052: Roles del legado en puestos — fusionar los iguales y quitar las asignaciones que sobran

**Estado:** Aceptada (modifica la migración de roles de ADR-039)

## Contexto

ADR-039 migró los 34 roles del legado uno a uno. En el legado cada rol era un privilegio, y un empleado acumulaba varios. En el modelo nuevo un rol es un puesto y el dónde lo pone la asignación, pero los datos siguieron con la forma vieja. En la base de desarrollo (35 roles, 1.179 asignaciones, 158 usuarios):

- **Roles idénticos.** `ROLE_CHECK_BOLETO_MOVIL` y `_WEB` conceden lo mismo, y `ROLE_ENTREGA_MULTIPLE_ENCOMIENDA` y `ROLE_RECEPTOR_ENCOMIENDAS` también.
- **Una negación sin efecto.** `ROLE_NO_VENDER_VOUCHER` no concede nada y tenía 32 asignaciones.
- **Roles sin puesto propio.** `ROLE_ADMIN_REIMPRIMIR_FACTURA` (54 asignaciones), `ROLE_SUPERVISOR_BOLETO_VOUCHER` (42) y `ROLE_EMISOR_CORTESIA` (13) solo repiten parte de lo que su titular ya tiene por otro rol. Ninguno aporta una capacidad a nadie.
- **Asignaciones que no aportan nada.** Dos de cada tres están cubiertas por otra del mismo usuario. El puesto más común en estación apila 6 a 8 roles.

## Decisión

Consolidar sin cambiar lo que puede nadie. Lo que amplía permisos no entra aquí: es una decisión de negocio (ver *Pendientes*).

- **Fusionar** (`EquivalenciasLegado::FUSIONES`): `ROLE_CHECK_BOLETO_WEB` y `_MOVIL` pasan a `ROLE_CHECK_BOLETO`, y `ROLE_ENTREGA_MULTIPLE_ENCOMIENDA` a `ROLE_RECEPTOR_ENCOMIENDAS`. Las asignaciones y las audiencias pasan al rol que queda. Si alguien editó uno y ya difieren en capacidades o límites, no se fusionan y se informa.
- **Quitar lo que sobra** (`ConsolidacionRoles::sobrantes`, puro): una asignación sobra si otra del usuario cubre cada una de sus capacidades en todo su ámbito, sin un límite más estricto, y el usuario sigue en las audiencias donde estaba. Solo se tocan las que puso la migración. Lo otorgado a mano y lo temporal se queda, y lo temporal tampoco cubre a nadie porque se acaba.
- **Borrar los roles sin uso** (`SIN_EFECTO`, `ABSORBIDOS`): solo si ya no tienen asignaciones ni audiencias. Un rol que todavía cubre a alguien se conserva y se informa.
- **Comprobar** (`ConsolidadorRoles`): compara ámbitos y límites de cada capacidad, y las empresas visibles, de cada usuario antes y después. Si algo cambia, lanza una excepción y no se guarda nada.

`app:acceso:consolidar-roles` simula (corre todo y deshace la transacción); con `--aplicar` guarda. Es idempotente y deja cada cambio en la bitácora de `usuario` y `rol`, con quién en blanco.

La importación del legado da el mismo resultado: `RolesUsuarioLegado::asignar` traduce los nombres (`EquivalenciasLegado::puesto`) y no crea lo que sobra, y `MigradorIAM` consolida al terminar. Un rol absorbido solo se crea si un usuario lo necesita.

## Consecuencias

**Positivas:**

- En la base de desarrollo: 35 → 29 roles y 1.179 → 425 asignaciones, sin que nadie gane ni pierda una capacidad.
- La pantalla de accesos de cada usuario muestra lo que importa, y `Delegacion` compara menos asignaciones.

**Negativas / pendientes:**

- `ROLE_NO_VENDER_VOUCHER` no concede nada pero iba sobre todas las estaciones. Quitarlo acota las estaciones de trabajo que pueden elegir 5 usuarios a las de sus roles. La estación actual de los 5 sigue dentro. El comando los lista.
- Revocar un puesto ahora quita también lo que antes repetía un rol menor. Quien solo debía perder parte necesita otro rol.
- Fusiones que sí amplían permisos, sin aplicar hasta validarlas con quien opera cada puesto (usuarios activos que ganarían algo):

| Fusión | Ganan algo | Qué ganan |
| --- | --- | --- |
| `ROLE_SUPERVISOR_AGENCIA` en `ROLE_ADMINISTRATIVOS` | 2 de 15 | Reportes y lectura de boletos, salidas y encomiendas |
| `ROLE_SUPERVISOR_BOLETO` y `ROLE_SUPERVISOR_ENCOMIENDA` | 7 de 80 | El área que no tenían: pueden operar y anular |
| `ROLE_REVISOR`, `ROLE_INSPECTOR_BOLETO`, `ROLE_INSPECTOR_ENCOMIENDA` y `ROLE_MANIFIESTO_ESPECIAL` | 30 de 50 | Solo lectura |
