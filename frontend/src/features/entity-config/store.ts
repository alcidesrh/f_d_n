/**
 * `useEntityConfigStore` — estado del editor de configuración de entidades
 * (ADR-010, ADR-043). Mantiene el listado de `entityClass`, los campos del
 * formulario de la entidad seleccionada, sus configuraciones del listado (la
 * por defecto y las de cada grupo de roles) con la que se está editando, y
 * los snapshots para detectar cambios sin guardar.
 *
 * Crear, renombrar, cambiar los roles y eliminar una configuración del
 * listado se guarda en el acto (`crearListado`, `guardarDatosListado`,
 * `eliminarListado`); columnas y opciones, con "Guardar cambios".
 *
 * `position` no se edita a mano: es la posición del panel en la lista, así que
 * se deriva del índice al construir el payload de guardado.
 */

import { defineStore } from 'pinia'
import {
  createListConfig,
  deleteListConfig,
  fetchEntityClasses,
  fetchEntityConfiguration,
  fetchListConfigs,
  saveEntityConfiguration,
  saveListConfig,
  type CollectionFieldConfigDto,
  type EntityConfigurationDetailDto,
  type FormFieldConfigDto,
  type ListConfigDto,
  type ListConfigsDto,
  type RolOpcion,
} from './api'
import { entityStores } from '@/core/entities/registry'
import type { ListOptions } from '@/core/entities/types'

/** Fila arrastrable: el DTO más una `key` estable (el IRI) para el drag & drop. */
export type CollectionFieldRow = CollectionFieldConfigDto & { key: string }
export type FormFieldRow = FormFieldConfigDto & { key: string }

export interface EntityConfigState {
  entityClasses: string[]
  selected: string
  /** Configuraciones del listado de la entidad, por prioridad. */
  listConfigs: ListConfigDto[]
  /** La que se edita (sus columnas y opciones están en `collectionFields` y `listOptions`). */
  listConfigId: number | null
  /** Roles que se pueden asociar a una configuración del listado. */
  roles: RolOpcion[]
  collectionFields: CollectionFieldRow[]
  formFields: FormFieldRow[]
  /** Opciones del listado de la entidad (filas por página, densidad…). */
  listOptions: ListOptions
  /** Snapshots serializados de la última carga/guardado: base de `dirty`. */
  baselineList: string
  baselineForm: string
  /** Keys (IRIs) de paneles con `attrs` que no es JSON válido: bloquean el guardado. */
  attrsErrors: string[]
  status: 'idle' | 'loading' | 'ready' | 'error'
  saving: boolean
  error: string
}

/** Ordena por `position` sin depender del orden que devuelva la API. */
function byPosition<T extends { position: number }>(fields: T[]): T[] {
  return [...fields].sort((a, b) => a.position - b.position)
}

/** Filas arrastrables ordenadas por `position`, con el IRI como `key` estable. */
function toRows<T extends { id: string | number; position: number }>(
  fields: T[] | undefined,
): Array<T & { key: string }> {
  return byPosition(fields ?? []).map((field) => ({ ...field, key: String(field.id) }))
}

/**
 * Cambia `visible` de una fila y la reubica con el mismo criterio que
 * `EntityConfiguration::orderFields()`: las ocultas van al final de la lista,
 * las visibles al final del bloque visible. Devuelve un array nuevo (la
 * lista arrastrable resincroniza por identidad, no en profundidad).
 */
function repositionByVisibility<T extends { key: string; visible: boolean }>(
  rows: T[],
  key: string,
  visible: boolean,
): T[] {
  const index = rows.findIndex((row) => row.key === key)
  const row = rows[index]
  if (!row) return rows

  row.visible = visible
  const rest = rows.filter((_, position) => position !== index)
  const firstHidden = rest.findIndex((item) => !item.visible)
  rest.splice(visible && firstHidden >= 0 ? firstHidden : rest.length, 0, row)
  return rest
}

/** Payload de un campo de listado; `position` es la posición del panel (1-based). */
function collectionPayload(row: CollectionFieldRow, index: number): CollectionFieldConfigDto {
  return {
    id: row.id,
    field: row.field,
    label: row.label ?? null,
    position: index + 1,
    visible: Boolean(row.visible),
    kind: row.kind ?? null,
    attrs: row.attrs ?? null,
    // Ordenar, filtrar y editar en línea se habilitan a mano: null y false son lo mismo (apagado).
    sortable: Boolean(row.sortable),
    filterable: Boolean(row.filterable),
    autocomplete: Boolean(row.autocomplete),
    editable: Boolean(row.editable),
    width: row.width?.trim() || null,
  }
}

function formPayload(row: FormFieldRow, index: number): FormFieldConfigDto {
  return {
    id: row.id,
    field: row.field,
    label: row.label ?? null,
    position: index + 1,
    visible: Boolean(row.visible),
    kind: row.kind ?? null,
    attrs: row.attrs ?? null,
    groupName: row.groupName ?? null,
  }
}

/** Solo las opciones con valor (lo vacío toma el valor por defecto del listado). */
function listOptionsPayload(options: ListOptions): ListOptions | null {
  const clean = Object.fromEntries(
    Object.entries(options).filter(([, value]) => value !== null && value !== undefined && !(Array.isArray(value) && !value.length)),
  ) as ListOptions
  return Object.keys(clean).length ? clean : null
}

function listSnapshot(collection: CollectionFieldRow[], options: ListOptions): string {
  return JSON.stringify({ collection: collection.map(collectionPayload), options: listOptionsPayload(options) })
}

function formSnapshot(form: FormFieldRow[]): string {
  return JSON.stringify(form.map(formPayload))
}

export const useEntityConfigStore = defineStore('entityConfig', {
  state: (): EntityConfigState => ({
    entityClasses: [],
    selected: '',
    listConfigs: [],
    listConfigId: null,
    roles: [],
    collectionFields: [],
    formFields: [],
    listOptions: {},
    baselineList: '',
    baselineForm: '',
    attrsErrors: [],
    status: 'idle',
    saving: false,
    error: '',
  }),

  getters: {
    /** Columnas u opciones de la configuración del listado elegida sin guardar (incluye el reordenamiento). */
    listDirty: (st): boolean => st.baselineList !== '' && st.baselineList !== listSnapshot(st.collectionFields, st.listOptions),
    formDirty: (st): boolean => st.baselineForm !== '' && st.baselineForm !== formSnapshot(st.formFields),
    /** Hay cambios sin guardar. */
    dirty(): boolean {
      return this.listDirty || this.formDirty
    },
    listConfig: (st): ListConfigDto | null => st.listConfigs.find((config) => config.id === st.listConfigId) ?? null,
    loading: (st): boolean => st.status === 'loading',
    canSave(): boolean {
      return this.dirty && this.attrsErrors.length === 0 && !this.saving
    },
  },

  actions: {
    /** Carga los nombres de entidad con configuración persistida. */
    async loadEntityClasses(force = false): Promise<string[]> {
      if (!force && this.entityClasses.length > 0) return this.entityClasses
      try {
        this.entityClasses = await fetchEntityClasses()
        this.error = ''
      } catch (error) {
        this.error = error instanceof Error ? error.message : String(error)
        console.error('[entityConfig] no se pudo listar las entidades:', error)
      }
      return this.entityClasses
    },

    /** Elige una entidad sin cargar su configuración (se carga con `select` al abrir el editor). */
    choose(entityClass: string): void {
      this.selected = entityClass
      this.status = 'idle'
      this.error = ''
      this.applyConfig(null)
    },

    /** Selecciona una entidad y carga su configuración (descarta cambios en curso). */
    async select(entityClass: string): Promise<void> {
      this.selected = entityClass
      this.status = 'loading'
      this.error = ''
      try {
        const [config, listados] = await Promise.all([fetchEntityConfiguration(entityClass), fetchListConfigs(entityClass)])
        if (!config) {
          this.applyConfig(null)
          this.status = 'error'
          this.error = `No hay configuración persistida para ${entityClass}`
          return
        }
        this.applyConfig(config)
        this.applyListConfigs(listados)
        this.status = 'ready'
      } catch (error) {
        this.applyConfig(null)
        this.status = 'error'
        this.error = error instanceof Error ? error.message : String(error)
        console.error(`[entityConfig] no se pudo cargar ${entityClass}:`, error)
      }
    },

    /** Recarga la entidad seleccionada desde el backend (descarta cambios locales). */
    async reload(): Promise<void> {
      if (this.selected) await this.select(this.selected)
    },

    /** Vuelca los campos del formulario en el estado y fija su snapshot. */
    applyConfig(config: EntityConfigurationDetailDto | null): void {
      this.formFields = toRows(config?.formFields)
      this.baselineForm = config ? formSnapshot(this.formFields) : ''
      if (!config) this.applyListConfigs(null)
      this.attrsErrors = []
    },

    /**
     * Vuelca las configuraciones del listado y edita `id` (o la que se estaba
     * editando, o la por defecto). Descarta las columnas y opciones sin guardar.
     */
    applyListConfigs(listados: ListConfigsDto | null, editar?: number | null): void {
      const id = editar === undefined ? this.listConfigId : editar
      this.listConfigs = listados?.configuraciones ?? []
      this.roles = listados?.roles ?? []
      const elegida =
        this.listConfigs.find((config) => config.id === id) ?? this.listConfigs.find((config) => config.porDefecto) ?? this.listConfigs[0] ?? null
      this.listConfigId = elegida?.id ?? null
      this.collectionFields = toRows(elegida?.collectionFieldConfig)
      this.listOptions = { ...elegida?.listOptions }
      this.baselineList = elegida ? listSnapshot(this.collectionFields, this.listOptions) : ''
      this.attrsErrors = this.attrsErrors.filter((key) => this.formFields.some((row) => key.startsWith(row.key)))
    },

    /** Edita otra configuración del listado (descarta columnas y opciones sin guardar). */
    chooseListConfig(id: number): void {
      this.applyListConfigs({ configuraciones: this.listConfigs, roles: this.roles }, id)
    },

    /** Nueva configuración del listado, copia de la elegida; queda elegida. */
    async crearListado(datos: { nombre: string; roles: number[] }): Promise<void> {
      const respuesta = await createListConfig(this.selected, { ...datos, desde: this.listConfigId ?? undefined })
      this.applyListConfigs(respuesta, respuesta.creada)
      await this.refrescarListado()
    },

    /** Nombre y roles de la elegida; conserva las columnas y opciones sin guardar. */
    async guardarDatosListado(datos: { nombre: string; roles: number[] }): Promise<void> {
      if (this.listConfigId === null) return
      const respuesta = await saveListConfig(this.selected, this.listConfigId, datos)
      this.listConfigs = respuesta.configuraciones
      this.roles = respuesta.roles
      await this.refrescarListado()
    },

    async eliminarListado(): Promise<void> {
      if (this.listConfigId === null) return
      this.applyListConfigs(await deleteListConfig(this.selected, this.listConfigId), null)
      await this.refrescarListado()
    },

    /** El listado abierto de la entidad toma lo guardado sin recargar la página. */
    async refrescarListado(): Promise<void> {
      await entityStores.get(this.selected)?.init(true)
    },

    /**
     * Alterna `visible` de un campo y lo reubica: al ocultarlo baja al final
     * de la lista, al mostrarlo sube al final de los visibles. Las posiciones
     * de todos los paneles se derivan del nuevo orden.
     */
    setVisible(variant: 'collection' | 'form', key: string, visible: boolean): void {
      if (variant === 'collection') {
        this.collectionFields = repositionByVisibility(this.collectionFields, key, visible)
      } else {
        this.formFields = repositionByVisibility(this.formFields, key, visible)
      }
    },

    /** Marca/limpia un panel cuyo `attrs` no es JSON válido. */
    setAttrsError(key: string, invalid: boolean): void {
      const errors = new Set(this.attrsErrors)
      if (invalid) errors.add(key)
      else errors.delete(key)
      if (errors.size !== this.attrsErrors.length) this.attrsErrors = [...errors]
    },

    /**
     * Guarda lo que cambió: los campos del formulario (GraphQL) y las
     * columnas y opciones de la configuración del listado elegida (REST).
     * Después refresca el store de la entidad si ya está instanciado, para
     * que el listado tome los cambios sin recargar la página.
     */
    async save(): Promise<void> {
      if (!this.selected || this.saving) return
      this.saving = true
      try {
        if (this.formDirty) {
          const saved = await saveEntityConfiguration({ entityClass: this.selected, formFields: this.formFields.map(formPayload) })
          this.applyConfig(saved)
        }
        if (this.listDirty && this.listConfigId !== null) {
          const listados = await saveListConfig(this.selected, this.listConfigId, {
            columnas: this.collectionFields.map(collectionPayload),
            listOptions: listOptionsPayload(this.listOptions),
          })
          this.applyListConfigs(listados)
        }
        this.error = ''
        await this.refrescarListado()
      } finally {
        this.saving = false
      }
    },
  },
})
