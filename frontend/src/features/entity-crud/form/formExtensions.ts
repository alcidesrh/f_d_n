/**
 * Secciones extra del formulario genérico por entidad (contrato en
 * `core/entities/formExtension`). A diferencia de `formOverrides`, el
 * formulario de la entidad sigue siendo el genérico: la sección se añade
 * debajo de sus campos y se guarda con él.
 *
 * El componente recibe `entity` e `id` (null al crear).
 */
import type { EntityFormLoader } from './formOverrides'

export interface EntityFormExtension {
  key: string
  title: string
  icon?: string
  component: EntityFormLoader
}

/** Clave = nombre de la entidad en PascalCase. */
export const entityFormExtensions: Record<string, EntityFormExtension[]> = {
  Bus: [
    {
      key: 'croquis',
      title: 'Croquis',
      icon: 'layout-grid',
      component: () => import('@/features/bus/BusCroquisSection.vue'),
    },
  ],
}
