# ADR-013: Renombrar la entidad Servicio a Recorrido

**Estado:** Aceptada

## Contexto

Tras ADR-011, la entidad que modela la salida concreta de un bus (trayecto + fecha + bus + piloto) se llamaba `Servicio`. El glosario de negocio (`CONTEXT.md`) y el habla del dominio se refieren a ese mismo concepto como "recorrido" — "es el recorrido como tal que hace el bus sobre un trayecto". Mantener el nombre de clase `Servicio` divergía del vocabulario de negocio y, además, colisionaba conceptualmente con el directorio técnico `src/Services/` de la aplicación (sin relación con el dominio), lo que podía inducir a confusión sobre si se trataba de un "servicio" de aplicación o de una entidad de negocio.

## Decisión

Se renombra la entidad `Servicio` a `Recorrido` (clase, tabla, repositorio, y toda referencia en código: `Recorrido.php`, `RecorridoRepository.php`, `BoletoAsiento::$recorrido`/`getRecorrido()`/`setRecorrido()`, acciones IAM `recorrido.*`). No hay relación con el `Recorrido` (plantilla) eliminado en ADR-011 — ese nombre quedó libre y se reutiliza aquí para el concepto correcto del glosario.

La tabla `servicio` se elimina y se crea `recorrido` (no fue un `RENAME TABLE`): al momento de este cambio ambas tablas estaban vacías en desarrollo, así que no hubo migración de datos que preservar.

## Consecuencias

**Positivas:**

- El nombre de la entidad coincide con el término que usa el negocio y el glosario, sin ambigüedad con `src/Services/`.
- Cierra el hueco de documentación que ADR-011 dejaba abierto (esa ADR habla de "recorrido" en prosa para referirse a algo llamado `Servicio` en código).

**Negativas:**

- Cualquier fixture, script SQL a mano o documentación externa que referenciara la tabla/clase `Servicio` queda rota y debe actualizarse (ver también ADR-011, actualizada para reflejar el nombre vigente).
- Segundo rename sobre el mismo concepto en poco tiempo (plantilla+instancia → Servicio → Recorrido): si vuelve a surgir la necesidad de una plantilla reutilizable de horarios (ver "Negativas" de ADR-011), habrá que elegir un nombre distinto para no repetir la colisión.
