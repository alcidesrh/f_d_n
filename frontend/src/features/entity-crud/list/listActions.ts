/**
 * Acciones extra del listado genérico, por entidad.
 *
 * - Por fila (`entityListActions`), junto a editar y eliminar: cada una abre
 *   su componente (un diálogo) con props `item` (la fila) y `visible` (v-model).
 * - Sobre la selección (`entityBulkActions`), en el modo selección: abren un
 *   diálogo con props `ids` (los ids numéricos) y `visible`, o ejecutan algo
 *   directamente (`ejecutar`) o llevan a otra pantalla (`ruta`).
 * - En la barra (`entityListLinks`): llevan a una pantalla propia de la
 *   entidad (p. ej. la jerarquía de roles).
 */
import type { RouteLocationRaw } from 'vue-router'
import type { EntityFormLoader } from '../form/formOverrides'

export interface EntityListAction {
  key: string
  label: string
  icon: string
  component: EntityFormLoader
}

/** Permiso de boletos que exige una acción sobre la selección (lo decide el backend). */
export type PermisoAccion = 'anular' | 'reasignar'

export interface EntityBulkAction {
  key: string
  label: string
  icon: string
  /** Si hace falta un permiso, la acción se oculta a quien no lo tiene. */
  permiso?: PermisoAccion
  /** Acción destructiva (se pinta en rojo). */
  severity?: 'danger'
  /** Diálogo con props `ids` y `visible`. */
  component?: EntityFormLoader
  /** Lleva a otra pantalla con los ids de la selección. */
  ruta?: (ids: number[]) => RouteLocationRaw
  /** Hace algo con los ids (y avisa lo que corresponda). */
  ejecutar?: (ids: number[]) => Promise<void>
}

/** Enlace de la barra del listado a una pantalla propia de la entidad. */
export interface EntityListLink {
  key: string
  label: string
  icon: string
  to: RouteLocationRaw
}

/** Clave = nombre de la entidad en PascalCase. */
export const entityListLinks: Record<string, EntityListLink[]> = {
  Role: [{ key: 'jerarquia', label: 'Jerarquía de roles', icon: 'account-tree-outline', to: { name: 'rol-jerarquia' } }],
}

/** Entidades cuyo listado no ofrece editar ni eliminar (solo sus acciones propias). */
export const entityReadOnly: ReadonlySet<string> = new Set(['BoletoAsiento'])

const bitacoraSalida: EntityListAction = {
  key: 'bitacora',
  label: 'Bitácora',
  icon: 'history',
  component: () => import('./BitacoraSalidaAccion.vue'),
}

const bitacoraBoleto: EntityListAction = {
  key: 'bitacora',
  label: 'Bitácora',
  icon: 'history',
  component: () => import('./BitacoraBoletoAccion.vue'),
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
  Salida: [bitacoraSalida],
  BoletoAsiento: [bitacoraBoleto],
}

/**
 * Borrado propio de una entidad, en lugar del eliminar genérico (fila y
 * selección): el diálogo recibe `ids` y `visible`, como las acciones sobre la selección.
 */
export const entityDeleteDialogs: Record<string, EntityFormLoader> = {
  Usuario: () => import('@/features/usuario/EliminarUsuariosDialog.vue'),
}

export const entityBulkActions: Record<string, EntityBulkAction[]> = {
  BoletoAsiento: [
    {
      key: 'reimprimir',
      label: 'Reimprimir ticket',
      icon: 'print-outline',
      ejecutar: async (ids) => {
        const { reimprimirBoletos } = await import('@/shared/boleto/reimprimir')
        await reimprimirBoletos(ids)
      },
    },
    {
      key: 'reasignar',
      label: 'Reasignar',
      icon: 'swap-horiz',
      permiso: 'reasignar',
      ruta: (ids) => ({ name: 'venta', query: { reasignar: ids.join(',') } }),
    },
    {
      key: 'anular',
      label: 'Anular',
      icon: 'cancel-outline',
      permiso: 'anular',
      component: () => import('@/shared/boleto/AnularBoletosDialog.vue'),
    },
  ],
}
