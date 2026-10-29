/**
 * Pone el estado guardado de una entidad al día con su metadata nueva
 * (ADR-036), con reglas que sirven para cualquier entidad:
 *
 * - filtros: quedan los que siguen siendo argumentos de la colección (con la
 *   misma forma: lista o valor suelto);
 * - orden: quedan las condiciones sobre campos que se siguen pudiendo ordenar;
 * - filtro a la medida: se quita entero si perdió algún argumento;
 * - datos en caché (`items`, `item`, `fullList`): se descartan;
 * - paginación: vuelve a la primera página.
 *
 * Las columnas y campos los pone al día la configuración (`mergeColumns`).
 * Puro.
 */
import type { EntitySchema, OrderCondition } from '@/core/graphql/types'
import type { EntityStoreState } from '@/core/entities/types'

type Reconciliable = Pick<EntityStoreState, 'filters' | 'order' | 'customFilter' | 'pagination'>
type Reconciliado = Pick<EntityStoreState, 'filters' | 'order' | 'customFilter' | 'items' | 'item' | 'fullList'> &
  Partial<Pick<EntityStoreState, 'pagination'>>

export function reconciliarEstado(estado: Reconciliable, entidad: EntitySchema): Reconciliado {
  const args = new Map(entidad.filterArgs.map((arg) => [arg.name, arg]))
  const sigue = (nombre: string, valor: unknown) => {
    const arg = args.get(nombre)
    return arg !== undefined && arg.isList === Array.isArray(valor)
  }
  const filters = Object.fromEntries(Object.entries(estado.filters).filter(([nombre, valor]) => sigue(nombre, valor)))
  const custom = estado.customFilter
  const customFilter =
    custom && Object.entries(custom.args).every(([nombre, valor]) => sigue(nombre, valor)) ? custom : null
  const ordenables = new Set(entidad.orderInput ? entidad.orderFields : [])
  const order = estado.order.filter((condicion: OrderCondition) =>
    Object.keys(condicion).every((campo) => ordenables.has(campo)),
  )

  return {
    filters,
    customFilter,
    order,
    items: [],
    item: null,
    fullList: [],
    ...(estado.pagination ? { pagination: { ...estado.pagination, currentPage: 1 } } : {}),
  }
}
