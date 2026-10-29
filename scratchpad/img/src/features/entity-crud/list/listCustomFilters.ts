/**
 * Filtro a la medida del listado genérico, por entidad (contrato en
 * `core/entities/listCustomFilter`). Se abre con su botón en la barra, sobre
 * la grilla, y es excluyente con los filtros por columna.
 *
 * El componente recibe `applied` (lo aplicado o null) y emite `apply`.
 */
import type { EntityFormLoader } from '../form/formOverrides'

export interface EntityCustomFilter {
  /** Texto del botón y del panel. */
  label: string
  icon: string
  component: EntityFormLoader
}

/** Clave = nombre de la entidad en PascalCase. */
export const entityCustomFilters: Record<string, EntityCustomFilter> = {
  BoletoTarifa: {
    label: 'Buscar tarifas',
    icon: 'manage-search',
    component: () => import('@/features/tarifa/TarifaFiltro.vue'),
  },
}
