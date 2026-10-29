/**
 * Filtro a la medida del listado genérico de una entidad: un panel propio
 * (p. ej. tarifas por los enclaves del trayecto) independiente de los filtros
 * por columna. La entidad lo registra en `features/entity-crud/list/listCustomFilters.ts`;
 * el componente vive en su feature y recibe/emite este contrato:
 *
 * - prop `applied`: lo aplicado (`values`), o null; al cambiar desde fuera
 *   (filtro de columna, restablecer) el panel vuelve a esos valores;
 * - evento `apply`: cada cambio de un valor, con los argumentos de la
 *   colección ya traducidos (`args`, todos se cumplen) y un resumen para los
 *   chips; null (o sin `args`) quita el filtro.
 *
 * Es excluyente con los filtros por columna: aplicar uno quita el otro. El
 * orden de las columnas no se toca. El estado vive en `store.customFilter`
 * (persistido) y el repositorio suma sus `args` a la consulta.
 *
 * El contrato vive en `core` para que la feature genérica y la dueña del
 * panel no se importen entre sí (ADR-017).
 */

export interface CustomFilterChip {
  label: string
  value: string
}

export interface CustomListFilterState {
  /** Valores del panel (para volver a mostrarlos). */
  values: Record<string, unknown>
  /** Argumentos de la colección GraphQL. */
  args: Record<string, unknown>
  /** Uno por término aplicado. */
  chips: CustomFilterChip[]
}

/** Lo aplicado, o null si no quedó ningún término. */
export function customFilterOrNull(state: CustomListFilterState | null | undefined): CustomListFilterState | null {
  return state && Object.keys(state.args).length > 0 ? state : null
}
