# ADR-014: Estados tipados por entidad (enum) en vez de catálogo Status genérico

**Estado:** Aceptada

## Contexto

`BoletoAsiento` y `Recorrido` usaban (directamente o vía `StatusTrait`) la entidad genérica `Status` (una tabla `status` con solo `nombre: string`, compartida entre `BoletoVenta`, `Cliente`, `Piloto`, `Usuario`, etc.). Nada impedía asignar un estado de una entidad a otra (p. ej. "De vacaciones" a un boleto), y no había forma de expresar ni validar en código las transiciones de negocio: qué estado puede seguir a cuál.

El negocio necesita dos máquinas de estado concretas:

- **BoletoAsiento**: `emitido → chequeado → transito → finalizado` en orden lineal; `anulado` y `reasignado` solo son alcanzables desde `emitido`.
- **Recorrido**: `programada → abordando → iniciada → finalizada` en orden lineal; `cancelada` solo es alcanzable desde `programada`.

## Decisión

Se reemplaza `Status`/`StatusTrait` en `BoletoAsiento` y `Recorrido` por enums PHP 8.1 nativos (`App\Entity\Enum\EstadoBoletoAsiento`, `App\Entity\Enum\EstadoRecorrido`), mapeados como columna `enumType` de Doctrine (mismo patrón ya usado por `AsientoClase`). Cada enum expone `transicionesPermitidas()` y `puedeTransicionarA()`; el setter de la entidad (`setEstado()`) valida la transición contra el estado actual y lanza `\DomainException` si es inválida — la regla de negocio vive en el propio enum/entidad, no en un servicio externo, así que se aplica también en mutaciones GraphQL que llamen a `setEstado()` directamente.

No se adoptó el componente `symfony/workflow` (sugerido como alternativa idiomática) para evitar añadir una dependencia nueva sin uso ya extendido en el proyecto; si en el futuro se necesitan guards más complejos (eventos, permisos por transición, auditoría de transición), migrar a `symfony/workflow` es la vía natural.

`Status` como entidad no se elimina: sigue siendo el catálogo usado por `BoletoVenta`, `Cliente`, `Piloto` y `Usuario`. Migrar esas entidades a enums propios queda fuera del alcance de esta decisión.

## Consecuencias

**Positivas:**

- Transiciones inválidas fallan en tiempo de ejecución con un mensaje claro, en vez de aceptar silenciosamente cualquier estado.
- Las reglas de transición quedan documentadas y testeables en un solo lugar (`transicionesPermitidas()`), sin necesidad de leer código disperso.
- Sin dependencia nueva ni columna FK adicional (el enum es una columna `VARCHAR`, no una tabla).

**Negativas:**

- `Status` sigue existiendo para otras entidades — el sistema queda temporalmente con dos patrones de estado convivientes (enum tipado vs. catálogo genérico) hasta que se decida migrar el resto.
- `setEstado()` es ahora estricto: código o fixtures que antes asignaban cualquier `Status` sin restricción deben construir la secuencia de transiciones válida (o partir del valor por defecto del enum).
