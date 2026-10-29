/**
 * Presentadores de columna del listado. Por defecto una relación se pide como
 * `X { id label }` y se muestra por su etiqueta; un presentador declara, para
 * un `Entidad.campo` concreto, qué subcampos pedir y cómo pintarlos. Pedir y
 * pintar viven juntos para que no se desincronicen.
 */
export interface ColumnPresenter {
  /** Subselección GraphQL de la relación (debe incluir `id`). */
  selection: string
  /** Texto de la celda a partir del valor de la relación. */
  text(value: Record<string, any>): string
}

const joinLabels = (separator = ' ', ...parts: Array<{ label?: unknown } | null | undefined>) =>
  parts
    .map((part) => (part?.label == null ? '' : String(part.label)))
    .filter(Boolean)
    .join(separator)

export const columnPresenters: Record<string, Record<string, ColumnPresenter>> = {
  BoletoTarifa: {
    trayecto: {
      selection: 'id\norigen {\n  label\n}\ndestino {\n  label\n}',
      text: (trayecto) => joinLabels(' ➔ ', trayecto.origen, trayecto.destino),
    },
  },
}

export function presenterFor(entity: string | undefined, field: string): ColumnPresenter | undefined {
  return entity ? columnPresenters[entity]?.[field] : undefined
}

/** Subselecciones por relación para las columnas pedidas que tienen presentador. */
export function selectionsFor(entity: string, fields: readonly string[]): Record<string, string> {
  const result: Record<string, string> = {}
  for (const field of fields) {
    const presenter = presenterFor(entity, field)
    if (presenter) result[field] = presenter.selection
  }
  return result
}
