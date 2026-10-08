/**
 * Acciones extra por fila en el listado genérico, junto a editar y eliminar.
 * Cada acción abre su componente (un diálogo) con props `item` (la fila) y
 * `visible` (v-model).
 */
import type { EntityFormLoader } from '../form/formOverrides'

export interface EntityListAction {
  key: string
  label: string
  icon: string
  component: EntityFormLoader
}

/** Clave = nombre de la entidad en PascalCase. */
export const entityListActions: Record<string, EntityListAction[]> = {
  Usuario: [
    {
      key: 'password',
      label: 'Cambiar contraseña',
      icon: 'password',
      component: () => import('@/features/usuario/CambiarPasswordDialog.vue'),
    },
  ],
}
