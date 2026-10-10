/**
 * Valores por defecto del listado de una entidad cuando su configuración
 * (`EntityConfiguration.listOptions`) no los fija.
 */
import type { ListOptions, ListViewState } from './types'

/** Filas por página y opciones del selector. */
export const DEFAULT_PAGE_SIZE = 10
export const DEFAULT_PAGE_SIZES = [10, 25, 50, 100]

/** Vista inicial (o tras "restablecer") según las opciones de la entidad. */
export function defaultView(options: ListOptions = {}): ListViewState {
  return {
    density: options.density ?? 'normal',
    layout: 'auto',
    filterMode: options.filterMode ?? 'or',
    filtersOpen: false,
  }
}
