/**
 * Qué ofrece el listado genérico en cada vista. El listado se monta en la
 * página `/lista/:entity`, en el editor de configuración y como selector del
 * chat; cada lugar puede apagar partes con la prop `features`.
 *
 * Precedencia: lo que la entidad no permite (sin mutación de update, de solo
 * lectura…) nunca se enciende; después manda `features` (prop), después las
 * opciones de la entidad (`listOptions`) y por último el modo (selector o no).
 */
import type { ListOptions } from '@/core/entities/types'

export interface ListFeatures {
  /** Barra de controles sobre el listado. */
  toolbar: boolean
  /** Mostrar/ocultar columnas (cabecera y menú de columnas). */
  columns: boolean
  /** Reordenar columnas arrastrando la cabecera. */
  reorder: boolean
  /** Cambiar el ancho de las columnas. */
  resize: boolean
  sort: boolean
  filter: boolean
  inlineEdit: boolean
  /** Columna fija de acciones por fila (editar, eliminar y las de la entidad). */
  rowActions: boolean
  /** Ofrecer el modo selección (en el selector siempre está activo). */
  selection: boolean
  /** "Eliminar seleccionados" entre las acciones de la selección. */
  bulkDelete: boolean
  /** "Enviar por chat" la selección. */
  share: boolean
  pagination: boolean
  /** Densidad y disposición (tabla/tarjetas). */
  view: boolean
  maximize: boolean
  reset: boolean
}

export interface FeatureContext {
  /** El listado es un selector (`v-model:seleccion`): siempre en selección, sin editar. */
  selector: boolean
  /** La entidad tiene mutación de update / delete. */
  canUpdate: boolean
  canDelete: boolean
  /** Entidad de solo lectura (`entityReadOnly`). */
  readOnly: boolean
  options?: ListOptions
  overrides?: Partial<ListFeatures>
}

const ALL_ON: ListFeatures = {
  toolbar: true,
  columns: true,
  reorder: true,
  resize: true,
  sort: true,
  filter: true,
  inlineEdit: true,
  rowActions: true,
  selection: true,
  bulkDelete: true,
  share: true,
  pagination: true,
  view: true,
  maximize: true,
  reset: true,
}

/** Lo que el selector no ofrece: es para elegir registros, no para operarlos. */
const SELECTOR_OFF: Partial<ListFeatures> = {
  inlineEdit: false,
  rowActions: false,
  bulkDelete: false,
  share: false,
  maximize: false,
}

export function resolveListFeatures(context: FeatureContext): ListFeatures {
  const fromOptions: Partial<ListFeatures> = {}
  if (context.options?.selectable === false) fromOptions.selection = false
  if (context.options?.inlineEdit === false) fromOptions.inlineEdit = false

  const features: ListFeatures = {
    ...ALL_ON,
    ...(context.selector ? SELECTOR_OFF : {}),
    ...fromOptions,
    ...context.overrides,
  }

  if (!context.canUpdate || context.readOnly) features.inlineEdit = false
  if (!context.canDelete || context.readOnly) features.bulkDelete = false
  // En el selector la selección es el propósito: no se puede apagar.
  if (context.selector) features.selection = true
  return features
}
