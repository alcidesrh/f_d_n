/**
 * Secciones extra del formulario genérico por entidad (contrato en
 * `core/entities/formExtension`). A diferencia de `formOverrides`, el
 * formulario de la entidad sigue siendo el genérico: con secciones, el
 * formulario se muestra en pestañas ("Datos" + una por sección, `title` e
 * `icon`) y todo se guarda con el mismo botón.
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
  Agencia: [
    {
      key: 'saldo',
      title: 'Saldo',
      icon: 'account-balance-wallet-outline',
      component: () => import('@/features/agencia/AgenciaSaldoSection.vue'),
    },
  ],
  Bus: [
    {
      key: 'croquis',
      title: 'Croquis',
      icon: 'grid-view-outline',
      component: () => import('@/features/bus/BusCroquisSection.vue'),
    },
  ],
}
