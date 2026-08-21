/**
 * Metadatos semánticos del schema GraphQL (API Platform), aplanados.
 *
 * La introspección cruda (tipos anidados, `ofType`, wrappers) se traduce a
 * esta estructura plana: cada entidad expuesta como recurso GraphQL sabe
 * cómo inferir su query item, su query collection, sus mutaciones, los
 * argumentos de filtro/paginación y qué seleccionar. Las relaciones se
 * aplanan a un solo nivel (`{ id label }`) para no recursar el schema.
 */

export type OrderDirection = 'ASC' | 'DESC'

/** Una condición de orden: `{ campo: "ASC" }` (shape de `[EntityNameFilter_order]`). */
export type OrderCondition = Record<string, OrderDirection>

export type CollectionKind = 'page-connection' | 'cursor-connection' | 'list' | 'single'

export interface SchemaArg {
  name: string
  /** Firma completa del tipo, ej: `Int`, `[String]`, `[UsuarioFilter_createdAt]`. */
  type: string
  /** Tipo desenvuelto (sin wrappers), ej: `Int`, `UsuarioFilter_createdAt`. */
  namedType: string
  required: boolean
  isList: boolean
}

export interface EntityFieldSchema {
  name: string
  type: string
  namedType: string
  /** Kind del tipo desenvuelto: SCALAR | ENUM | OBJECT. */
  kind: string
  required: boolean
  isList: boolean
  /** Relación con otra entidad (se selecciona `{ id label }`). */
  isRelation: boolean
  /** Subrecurso tipo connection (ej: `ventas(...): VentaPageConnection`). */
  isSubcollection: boolean
  /** Valores de un ENUM (vacío si no aplica), para selects de formularios. */
  enumValues: string[]
}

export interface SchemaInputField {
  name: string
  type: string
  namedType: string
  kind: string
  required: boolean
  isList: boolean
  /** La entrada espera un IRI/id de una entidad (relación). */
  isRelation: boolean
  /** Valores de un ENUM (vacío si no aplica), para selects de formularios. */
  enumValues: string[]
}

export interface MutationSchema {
  kind: 'create' | 'update' | 'delete'
  /** Campo en el root `Mutation`, ej: `createBoleto`. */
  field: string
  /** Tipo input, ej: `createBoletoInput`. */
  inputType: string
  /** Tipo payload, ej: `createBoletoPayload`. */
  payloadType: string
  /** Campo del payload que devuelve la entidad, ej: `boleto`. */
  returnsField: string
  /** Campos planos del input (útil para formularios). */
  inputFields: SchemaInputField[]
}

export interface EntitySchema {
  name: string
  /** Campo del root Query para el item, ej: `boleto`. Null si no expone. */
  queryItem: string | null
  /** Campo del root Query para la colección, ej: `boletos`. Null si no expone. */
  queryCollection: string | null
  collectionKind: CollectionKind | null
  /** Tipo retornado por la colección, ej: `BoletoPageConnection`. */
  collectionType: string | null
  /** Tipo de paginación (paginationInfo/pageInfo), ej: `BoletoPaginationInfo`. */
  paginationType: string | null
  /** Input de orden, ej: `BoletoFilter_order`. Null si no hay orden. */
  orderInput: string | null
  /** Campos ordenables según el input de orden. */
  orderFields: string[]
  itemArgs: SchemaArg[]
  collectionArgs: SchemaArg[]
  /** Argumentos de colección que son filtros (paginación/orden excluidos). */
  filterArgs: SchemaArg[]
  /** Todos los campos propios de la entidad (plano). */
  fields: EntityFieldSchema[]
  /** Campos hoja (scalars/enums) seleccionables en un listado. */
  scalarFields: string[]
  /** Relaciones (objetos/listas de objetos) aplanadas a un nivel. */
  relations: EntityFieldSchema[]
  /** Subrecursos tipo connection (no se seleccionan por defecto). */
  subcollections: EntityFieldSchema[]
  create: MutationSchema | null
  update: MutationSchema | null
  delete: MutationSchema | null
}

export interface CollectionPagination {
  currentPage: number
  itemsPerPage: number
  lastPage: number
  totalCount: number
  hasNextPage: boolean
}

/** Resultado normalizado de una colección, sin el hull de la connection. */
export interface CollectionResult<T> {
  items: T[]
  pagination: CollectionPagination
}

/** Opción de `fullList` (query `collectionAgnostic`): `{ id: IRI, label }`. */
export interface AgnosticOption {
  id: string
  label: string
}
