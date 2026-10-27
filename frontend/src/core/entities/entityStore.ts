/**
 * Fábrica de stores de entidad. `defineEntityStore(name)` devuelve la
 * definición Pinia (`entity:{name}`, persistida: el listado se reencuentra
 * como se dejó). Se usa vía `getEntity(name)` (`registry.ts`).
 *
 * Lo persistido sirve mientras no cambie la metadata (ADR-036): la
 * configuración se vuelve a pedir solo si el manifiesto dice que cambió
 * después de pedirla (`revisionConfig`), y filtros, orden y caché se ajustan
 * si cambió la metadata de la entidad (`huellaEsquema`).
 */
import { defineStore } from 'pinia'
import { alDescartar } from '@/core/auth/alcanceSesion'
import { fetchEntityConfiguration } from '@/core/metadata/entityConfiguration'
import { manifiestoActual, revisionVigenteDeConfig } from '@/core/metadata/manifiesto'
import { reconciliarEstado } from '@/core/metadata/reconciliar'
import { repository } from './repository'
import { useSchemaStore } from './schema'
import { entitySlug } from './slug'
import { DEFAULT_PAGE_SIZE, defaultView } from './listView'
import { columnaDeConfig, mergeColumns } from './columnMerge'
import type { CollectionFieldConfig, EntityStore, EntityStoreState } from './types'

const DEFAULT_PAGINATION = {
  itemsPerPage: DEFAULT_PAGE_SIZE,
  currentPage: 1,
  totalCount: 0,
  lastPage: 1,
  hasNextPage: false,
}

/** Carga de configuración en curso por entidad (evita pedirla dos veces a la vez). */
const configRequests = new Map<string, Promise<void>>()

/**
 * Entidades cuya configuración se pidió en esta página. Sin manifiesto (no
 * llegó o falló) no se sabe si cambió: se pide una vez por página.
 */
const pedidas = new Set<string>()
alDescartar(() => pedidas.clear())

/**
 * Columnas por defecto cuando el backend no tiene `entity_configurations`:
 * todas las propiedades escalares (menos `id`), sin ordenar ni filtrar (se
 * habilitan en la configuración).
 */
export function buildFallbackColumns(name: string): CollectionFieldConfig[] {
  const schema = useSchemaStore().require(name)
  return schema.scalarFields
    .filter((field) => field !== 'id' && field !== '_id')
    .map((field, index) => ({
      field,
      label: field,
      position: index + 1,
      visible: true,
      sortable: false,
      filterable: false,
      showFilter: false,
    }))
}

function createEntityStore(name: string) {
  const schema = useSchemaStore()
  const entity = schema.require(name)
  const paginated = entity.collectionKind === 'page-connection'

  return defineStore(`entity:${entity.name}`, {
    persist: true,
    state: (): EntityStoreState => ({
      name: entity.name,
      columns: [],
      formFields: [],
      items: [],
      filters: {},
      customFilter: null,
      order: [],
      item: null,
      fullList: [],
      listOptions: {},
      listConfigId: null,
      view: defaultView(),
      huellaEsquema: null,
      revisionConfig: null,
      ...(paginated ? { pagination: { ...DEFAULT_PAGINATION } } : {}),
    }),
    getters: {
      metadata: (st) => schema.require(st.name),
      slug: (st) => entitySlug(st.name),
    },
    actions: {
      reconciliar() {
        const entity = schema.find(this.name)
        const huella = schema.huellas[this.name] ?? null
        if (!entity || huella === null || huella === this.huellaEsquema) return
        const reconciliado = reconciliarEstado(this, entity)
        this.$patch((state) => Object.assign(state, reconciliado))
        this.huellaEsquema = huella
      },
      async init(force = false) {
        this.reconciliar()
        const vigente = revisionVigenteDeConfig(this.name)
        const alDia =
          this.columns.length > 0 &&
          this.revisionConfig !== null &&
          (vigente === null ? pedidas.has(this.name) : this.revisionConfig >= vigente)
        if (!force && alDia) return
        let request = configRequests.get(this.name)
        if (!request) {
          // La del manifiesto antes de pedir: lo que llegue es por lo menos de esa revisión.
          const revision = manifiestoActual.value?.revision ?? 0
          // Recargar todo (publicación manual): también los datos guardados.
          if (this.revisionConfig !== null && (manifiestoActual.value?.completa ?? 0) > this.revisionConfig) {
            this.items = []
            this.item = null
            this.fullList = []
          }
          request = fetchEntityConfiguration(this.name)
            .then((config) => {
              // Otra configuración del listado (cambiaron sus roles): la vista guardada era de la anterior.
              const otra = (config?.listConfig?.id ?? null) !== (this.listConfigId ?? null)
              const firstLoad = this.columns.length === 0 || otra
              this.listConfigId = config?.listConfig?.id ?? null
              this.listOptions = config?.listOptions ?? {}
              if (firstLoad || force) this.view = defaultView(this.listOptions)
              if (this.pagination && (firstLoad || force))
                this.pagination.itemsPerPage = this.listOptions.pageSize ?? DEFAULT_PAGE_SIZE
              this.formFields = config?.formFields ?? []
              const columns = config?.collectionFieldConfig ?? []
              // Con `force` (guardar o restablecer) se toma el servidor tal cual; si no, se respeta la vista del usuario.
              this.columns = columns.length
                ? force || otra
                  ? columns.map(columnaDeConfig)
                  : mergeColumns(this.columns, columns)
                : buildFallbackColumns(this.name)
              this.revisionConfig = revision
              pedidas.add(this.name)
            })
            .catch((error: unknown) =>
              console.warn(`[entity:${this.name}] sin configuración REST:`, error),
            )
            .finally(() => configRequests.delete(this.name))
          configRequests.set(this.name, request)
        }
        await request
      },
      async fetchItems() {
        await repository.collection(this as unknown as EntityStore)
        return this.items
      },
      fetchItem(id: string | number, fields?: string[]) {
        return repository.item(this as unknown as EntityStore, id, fields)
      },
      create(data: Record<string, unknown>) {
        return repository.create(this as unknown as EntityStore, data)
      },
      update(data: Record<string, unknown>) {
        return repository.update(this as unknown as EntityStore, data)
      },
      remove(id: string | number) {
        return repository.delete(this as unknown as EntityStore, id)
      },
      loadFullList(force = false) {
        return repository.fullList(this as unknown as EntityStore, { force })
      },
    },
  })
}

const definitions = new Map<string, ReturnType<typeof createEntityStore>>()

export function defineEntityStore(name: string) {
  let definition = definitions.get(name)
  if (!definition) {
    definition = createEntityStore(name)
    definitions.set(name, definition)
  }
  return definition
}

/** La entidad dejó de existir en el schema: si vuelve, su definición se arma con la metadata nueva. */
export function olvidarDefinicion(name: string): void {
  definitions.delete(name)
  pedidas.delete(name)
}
