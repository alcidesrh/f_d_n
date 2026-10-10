/**
 * Contratos de los stores de entidad (`entity:{Nombre}`): estado de listado y
 * formulario de una entidad de la API. Las operaciones de datos viven en
 * `repository.ts`; el store solo las expone como acciones.
 */
import type { AgnosticOption, EntitySchema, OrderCondition } from "@/core/graphql/types";
import type { FormFieldConfig } from "@/core/metadata/entityConfiguration";

export type { OrderCondition };

/** Configuración de una columna del listado (DTO REST de `entity_configurations`). */
export interface CollectionFieldConfig {
  "@id"?: string;
  id?: number | string;
  field: string;
  name?: string;
  label?: string | null;
  position?: number;
  visible?: boolean;
  sortable?: boolean | null;
  filterable?: boolean | null;
  showFilter?: boolean | null;
  attrs?: Record<string, unknown> | null;
  kind?: string;
  /** Ancho: preset (`xs`…`xl`), longitud (`12rem`) o fracción (`2fr`). Null: flexible. */
  width?: string | null;
  /** El `width` de la configuración (el usuario puede cambiar `width` arrastrando el borde). */
  configWidth?: string | null;
  [key: string]: unknown;
}

export type ListDensity = "compact" | "normal" | "comfortable";
export type ListLayout = "auto" | "table" | "cards";
/** `or`: basta con un filtro; `and`: se cumplen todos. */
export type FilterMode = "or" | "and";

/** Opciones del listado de la entidad (`EntityConfiguration.listOptions`); lo ausente toma el valor por defecto. */
export interface ListOptions {
  pageSize?: number;
  pageSizes?: number[];
  density?: ListDensity;
  filterMode?: FilterMode;
  /** Ofrecer el modo selección. */
  selectable?: boolean;
  /** Permitir la edición en línea. */
  inlineEdit?: boolean;
}

/** Cómo dejó el usuario el listado (persistido con el store). */
export interface ListViewState {
  density: ListDensity;
  layout: ListLayout;
  filterMode: FilterMode;
  /** Fila de filtros abierta. */
  filtersOpen: boolean;
}

export interface PaginationState {
  itemsPerPage: number;
  currentPage: number;
  totalCount: number;
  lastPage: number;
  hasNextPage: boolean;
}

export interface EntityStoreState<T = unknown> {
  /** Nombre de la entidad (`BoletoAsiento`). */
  name: string;
  /** Columnas del listado (`entity_configurations` o todas las propiedades escalares). */
  columns: CollectionFieldConfig[];
  /** Campos del formulario (`entity_configurations`); vacío = todos los del schema. */
  formFields: FormFieldConfig[];
  items: T[];
  /** Solo en entidades paginadas (`page-connection`). */
  pagination?: PaginationState;
  filters: Record<string, unknown>;
  order: OrderCondition[];
  /** Último item leído o guardado. */
  item: T | null;
  /** Todos los registros como options (`collectionAgnostic`). */
  fullList: AgnosticOption[];
  /** Opciones del listado configuradas para la entidad. */
  listOptions: ListOptions;
  view: ListViewState;
}

export interface EntityStore<T = unknown> extends EntityStoreState<T> {
  $id: string;
  $patch: (partial: Partial<EntityStoreState<T>>) => void;
  $reset: () => void;

  /** Metadata GraphQL de la entidad. */
  readonly metadata: EntitySchema;
  /** Slug para URLs (`BoletoAsiento` → `boleto-asiento`). */
  readonly slug: string;

  /** Carga la configuración de columnas/campos (una vez, salvo `force`). */
  init(force?: boolean): Promise<void>;
  fetchItems(): Promise<T[]>;
  /** Item por id o IRI; con `fields` solo se piden esos campos (+ `id`). */
  fetchItem(id: string | number, fields?: string[]): Promise<T>;
  create(data: Record<string, unknown>): Promise<T>;
  update(data: Record<string, unknown>): Promise<T>;
  remove(id: string | number): Promise<T>;
  loadFullList(force?: boolean): Promise<AgnosticOption[]>;
}
