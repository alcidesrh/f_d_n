/**
 * Contratos de los stores de entidad (`entity:{Nombre}`): estado de listado y
 * formulario de una entidad de la API. Las operaciones de datos viven en
 * `repository.ts`; el store solo las expone como acciones.
 */
import type { AgnosticOption, EntitySchema, OrderCondition } from '@/core/graphql/types'
import type { FormFieldConfig } from '@/core/metadata/entityConfiguration'

export type { OrderCondition }

/** Configuración de una columna del listado (DTO REST de `entity_configurations`). */
export interface CollectionFieldConfig {
  '@id'?: string
  id?: number | string
  field: string
  name?: string
  label?: string | null
  position?: number
  visible?: boolean
  sortable?: boolean | null
  filterable?: boolean | null
  showFilter?: boolean | null
  attrs?: Record<string, unknown> | null
  [key: string]: unknown
}

export interface PaginationState {
  itemsPerPage: number
  currentPage: number
  totalCount: number
  lastPage: number
  hasNextPage: boolean
}

export interface EntityStoreState<T = unknown> {
  /** Nombre de la entidad (`BoletoAsiento`). */
  name: string
  /** Columnas del listado (`entity_configurations` o todas las propiedades escalares). */
  columns: CollectionFieldConfig[]
  /** Campos del formulario (`entity_configurations`); vacío = todos los del schema. */
  formFields: FormFieldConfig[]
  items: T[]
  /** Solo en entidades paginadas (`page-connection`). */
  pagination?: PaginationState
  filters: Record<string, unknown>
  order: OrderCondition[]
  /** Último item leído o guardado. */
  item: T | null
  /** Todos los registros como options (`collectionAgnostic`). */
  fullList: AgnosticOption[]
}

export interface EntityStore<T = unknown> extends EntityStoreState<T> {
  $id: string
  $patch: (partial: Partial<EntityStoreState<T>>) => void
  $reset: () => void

  /** Metadata GraphQL de la entidad. */
  readonly metadata: EntitySchema
  /** Slug para URLs (`BoletoAsiento` → `boleto-asiento`). */
  readonly slug: string

  /** Carga la configuración de columnas/campos (una vez, salvo `force`). */
  init(force?: boolean): Promise<void>
  fetchItems(): Promise<T[]>
  /** Item por id o IRI; con `fields` solo se piden esos campos (+ `id`). */
  fetchItem(id: string | number, fields?: string[]): Promise<T>
  create(data: Record<string, unknown>): Promise<T>
  update(data: Record<string, unknown>): Promise<T>
  remove(id: string | number): Promise<T>
  loadFullList(force?: boolean): Promise<AgnosticOption[]>
}
