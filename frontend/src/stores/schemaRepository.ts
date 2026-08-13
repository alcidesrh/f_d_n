/**
 * `useSchemaRepositoryStore` — punto de entrada a la API GraphQL.
 *
 * Se crea después del cliente Apollo singleton (`src/lib/apollo/client.ts`)
 * y antes de montar cualquier vista. En su primera creación ejecuta la
 * introspección GraphQL y la traduce a metadatos planos por entidad; el
 * estado persistido (pinia-plugin-persistedstate) evita reintrospeccionar.
 *
 * Sus actions (`item`, `collection`, `create`, `update`, `delete`) reciben
 * siempre el store de la entidad (`use{EntityName}Store`): leen su estado
 * (paginación/filtros/orden) y actualizan sus items/item.
 */

import { defineStore } from 'pinia'
import { apollo } from '@/lib/apollo'
import { toMutationInput } from '@/lib/apollo/documents'
import type { CollectionResult, EntitySchema } from '@/lib/apollo/types'
import type { EntityStore } from './entities/types'

/** Bump para invalidar el schema persistido cuando cambia el formato de metadatos. */
export const SCHEMA_REPOSITORY_VERSION = 1

export interface SchemaRepositoryState {
  status: 'idle' | 'loading' | 'ready' | 'error'
  error: string
  entities: Record<string, EntitySchema>
  schemaVersion: number
  loadedAt: string | null
}

export const useSchemaRepositoryStore = defineStore('schemaRepository', {
  persist: {
    pick: ['entities', 'schemaVersion', 'loadedAt'],
  },

  state: (): SchemaRepositoryState => ({
    status: 'idle',
    error: '',
    entities: {},
    schemaVersion: SCHEMA_REPOSITORY_VERSION,
    loadedAt: null,
  }),

  getters: {
    ready: (st): boolean => st.status === 'ready',
    hasEntities: (st): boolean => Object.keys(st.entities).length > 0,
  },

  actions: {
    /**
     * Carga (o reusa el persistido) del metadata de entidades. Llamar una
     * vez en el bootstrap, antes de renderizar las vistas.
     */
    async init() {
      if (this.status === 'loading') return
      if (this.schemaVersion !== SCHEMA_REPOSITORY_VERSION) this.entities = {}
      if (this.hasEntities) {
        this.status = 'ready'
        return
      }
      this.status = 'loading'
      this.error = ''
      try {
        this.entities = await apollo.introspect()
        this.loadedAt = new Date().toISOString()
        this.schemaVersion = SCHEMA_REPOSITORY_VERSION
        this.status = 'ready'
        console.log(this.entities)
      } catch (error) {
        this.status = 'error'
        this.error = error instanceof Error ? error.message : String(error)
        console.error('[schemaRepository] no se pudo cargar el schema GraphQL:', error)
      }
    },

    getEntity(name: string): EntitySchema | null {
      return this.entities[name] ?? null
    },

    has(name: string): boolean {
      return Boolean(this.entities[name])
    },

    requireEntity<T>(store: EntityStore<T>): EntitySchema {
      const entity = this.entities[store.name]
      if (!entity) {
        throw new Error(`[schemaRepository] no hay metadatos para "${store.name}"`)
      }
      return entity
    },

    async item<T>(store: EntityStore<T>, id: string | number): Promise<T> {
      const entity = this.requireEntity(store)
      store.item = await apollo.item<T>(entity, id)
      return store.item
    },

    async collection<T>(store: EntityStore<T>): Promise<CollectionResult<T>> {
      const entity = this.requireEntity(store)
      const result = await apollo.collection<T>(entity, {
        currentPage: store.pagination.currentPage,
        itemsPerPage: store.pagination.itemsPerPage,
        filters: store.filters,
        order: store.order,
      })
      store.items = result.items
      store.pagination = {
        ...result.pagination,
        itemsPerPage: store.pagination.itemsPerPage || result.pagination.itemsPerPage,
      }
      return result
    },

    async create<T>(store: EntityStore<T>, data: Record<string, unknown>): Promise<T> {
      const entity = this.requireEntity(store)
      if (!entity.create) {
        throw new Error(`[schemaRepository] "${store.name}" no expone create`)
      }
      const input = toMutationInput(entity, entity.create, data, this.entities)
      const created = await apollo.create<T>(entity, input)
      store.item = created
      store.items = [created, ...store.items]
      return created
    },

    async update<T>(store: EntityStore<T>, data: Record<string, unknown>): Promise<T> {
      const entity = this.requireEntity(store)
      if (!entity.update) {
        throw new Error(`[schemaRepository] "${store.name}" no expone update`)
      }
      const input = toMutationInput(entity, entity.update, data, this.entities)
      const updated = await apollo.update<T>(entity, input)
      store.item = updated
      const id = (updated as { id?: unknown } | null)?.id
      if (id !== undefined) {
        store.items = store.items.map((item) =>
          (item as { id?: unknown } | null)?.id === id ? updated : item,
        )
      }
      return updated
    },

    async delete<T>(store: EntityStore<T>, id: string | number): Promise<T> {
      const entity = this.requireEntity(store)
      if (!entity.delete) {
        throw new Error(`[schemaRepository] "${store.name}" no expone delete`)
      }
      const deleted = await apollo.delete<T>(entity, id)
      store.items = store.items.filter((item) => (item as { id?: unknown } | null)?.id !== id)
      if ((store.item as { id?: unknown } | null)?.id === id) store.item = null
      return deleted
    },
  },
})

// {
//   "Agnostic": {
//     "name": "Agnostic",
//     "queryItem": "agnostic",
//     "queryCollection": null,
//     "collectionKind": null,
//     "collectionType": null,
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "data",
//         "type": "Iterable!",
//         "namedType": "Iterable",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "data",
//       "_id"
//     ],
//     "relations": [],
//     "subcollections": [],
//     "create": null,
//     "update": null,
//     "delete": {
//       "kind": "delete",
//       "field": "deleteAgnostic",
//       "inputType": "deleteAgnosticInput",
//       "payloadType": "deleteAgnosticPayload",
//       "returnsField": "agnostic",
//       "inputFields": [
//         {
//           "name": "ids",
//           "type": "[ID]",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "resource",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "ConfigVersions": {
//     "name": "ConfigVersions",
//     "queryItem": "configVersions",
//     "queryCollection": null,
//     "collectionKind": null,
//     "collectionType": null,
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "versions",
//         "type": "Iterable!",
//         "namedType": "Iterable",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "versions"
//     ],
//     "relations": [],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createConfigVersions",
//       "inputType": "createConfigVersionsInput",
//       "payloadType": "createConfigVersionsPayload",
//       "returnsField": "configVersions",
//       "inputFields": [
//         {
//           "name": "versions",
//           "type": "Iterable!",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateConfigVersions",
//       "inputType": "updateConfigVersionsInput",
//       "payloadType": "updateConfigVersionsPayload",
//       "returnsField": "configVersions",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "versions",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteConfigVersions",
//       "inputType": "deleteConfigVersionsInput",
//       "payloadType": "deleteConfigVersionsPayload",
//       "returnsField": "configVersions",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "EntityConfigurationDto": {
//     "name": "EntityConfigurationDto",
//     "queryItem": "entityConfigurationDto",
//     "queryCollection": "entityConfigurationDtos",
//     "collectionKind": "cursor-connection",
//     "collectionType": "EntityConfigurationDtoCursorConnection",
//     "paginationType": "EntityConfigurationDtoPageInfo",
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "first",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "last",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "before",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "after",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "collectionFieldConfig",
//         "type": "Iterable!",
//         "namedType": "Iterable",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "formFields",
//         "type": "Iterable!",
//         "namedType": "Iterable",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "collectionFieldConfig",
//       "formFields"
//     ],
//     "relations": [],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createEntityConfigurationDto",
//       "inputType": "createEntityConfigurationDtoInput",
//       "payloadType": "createEntityConfigurationDtoPayload",
//       "returnsField": "entityConfigurationDto",
//       "inputFields": [
//         {
//           "name": "collectionFieldConfig",
//           "type": "Iterable!",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "formFields",
//           "type": "Iterable!",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateEntityConfigurationDto",
//       "inputType": "updateEntityConfigurationDtoInput",
//       "payloadType": "updateEntityConfigurationDtoPayload",
//       "returnsField": "entityConfigurationDto",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "collectionFieldConfig",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "formFields",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteEntityConfigurationDto",
//       "inputType": "deleteEntityConfigurationDtoInput",
//       "payloadType": "deleteEntityConfigurationDtoPayload",
//       "returnsField": "entityConfigurationDto",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Action": {
//     "name": "Action",
//     "queryItem": "action",
//     "queryCollection": "actions",
//     "collectionKind": "list",
//     "collectionType": "Action",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "ruta",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "roles",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "permisos",
//         "type": "[Permiso]",
//         "namedType": "Permiso",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "icon",
//         "type": "Icon",
//         "namedType": "Icon",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "rutaParams",
//         "type": "Iterable",
//         "namedType": "Iterable",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "ruta",
//       "nombre",
//       "rutaParams",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "roles",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "permisos",
//         "type": "[Permiso]",
//         "namedType": "Permiso",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "icon",
//         "type": "Icon",
//         "namedType": "Icon",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createAction",
//       "inputType": "createActionInput",
//       "payloadType": "createActionPayload",
//       "returnsField": "action",
//       "inputFields": [
//         {
//           "name": "ruta",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "roles",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "permisos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "icon",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "rutaParams",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateAction",
//       "inputType": "updateActionInput",
//       "payloadType": "updateActionPayload",
//       "returnsField": "action",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "ruta",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "roles",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "permisos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "icon",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "rutaParams",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteAction",
//       "inputType": "deleteActionInput",
//       "payloadType": "deleteActionPayload",
//       "returnsField": "action",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Role": {
//     "name": "Role",
//     "queryItem": "role",
//     "queryCollection": "roles",
//     "collectionKind": "list",
//     "collectionType": "Role",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "parents",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "children",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "permisos",
//         "type": "[Permiso]",
//         "namedType": "Permiso",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "actions",
//         "type": "[Action]",
//         "namedType": "Action",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "nombre",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "parents",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "children",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "permisos",
//         "type": "[Permiso]",
//         "namedType": "Permiso",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "actions",
//         "type": "[Action]",
//         "namedType": "Action",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createRole",
//       "inputType": "createRoleInput",
//       "payloadType": "createRolePayload",
//       "returnsField": "role",
//       "inputFields": [
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "parents",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "children",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "permisos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "actions",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateRole",
//       "inputType": "updateRoleInput",
//       "payloadType": "updateRolePayload",
//       "returnsField": "role",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "parents",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "children",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "permisos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "actions",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteRole",
//       "inputType": "deleteRoleInput",
//       "payloadType": "deleteRolePayload",
//       "returnsField": "role",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Permiso": {
//     "name": "Permiso",
//     "queryItem": "permiso",
//     "queryCollection": "permisos",
//     "collectionKind": "list",
//     "collectionType": "Permiso",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "roles",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "parents",
//         "type": "[Permiso]",
//         "namedType": "Permiso",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "children",
//         "type": "[Permiso]",
//         "namedType": "Permiso",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "actions",
//         "type": "[Action]",
//         "namedType": "Action",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nota",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "nombre",
//       "nota",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "roles",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "parents",
//         "type": "[Permiso]",
//         "namedType": "Permiso",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "children",
//         "type": "[Permiso]",
//         "namedType": "Permiso",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "actions",
//         "type": "[Action]",
//         "namedType": "Action",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createPermiso",
//       "inputType": "createPermisoInput",
//       "payloadType": "createPermisoPayload",
//       "returnsField": "permiso",
//       "inputFields": [
//         {
//           "name": "roles",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "parents",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "children",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "actions",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nota",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updatePermiso",
//       "inputType": "updatePermisoInput",
//       "payloadType": "updatePermisoPayload",
//       "returnsField": "permiso",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "roles",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "parents",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "children",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "actions",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nota",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deletePermiso",
//       "inputType": "deletePermisoInput",
//       "payloadType": "deletePermisoPayload",
//       "returnsField": "permiso",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Status": {
//     "name": "Status",
//     "queryItem": "status",
//     "queryCollection": "statuses",
//     "collectionKind": "list",
//     "collectionType": "Status",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "nombre",
//       "_id",
//       "label"
//     ],
//     "relations": [],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createStatus",
//       "inputType": "createStatusInput",
//       "payloadType": "createStatusPayload",
//       "returnsField": "status",
//       "inputFields": [
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateStatus",
//       "inputType": "updateStatusInput",
//       "payloadType": "updateStatusPayload",
//       "returnsField": "status",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteStatus",
//       "inputType": "deleteStatusInput",
//       "payloadType": "deleteStatusPayload",
//       "returnsField": "status",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Icon": {
//     "name": "Icon",
//     "queryItem": "icon",
//     "queryCollection": "icons",
//     "collectionKind": "page-connection",
//     "collectionType": "IconPageConnection",
//     "paginationType": "IconPaginationInfo",
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "icon",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "name",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       }
//     ],
//     "filterArgs": [
//       {
//         "name": "icon",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "name",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       }
//     ],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "icon",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "name",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "icon",
//       "name",
//       "_id",
//       "label"
//     ],
//     "relations": [],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createIcon",
//       "inputType": "createIconInput",
//       "payloadType": "createIconPayload",
//       "returnsField": "icon",
//       "inputFields": [
//         {
//           "name": "icon",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "name",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateIcon",
//       "inputType": "updateIconInput",
//       "payloadType": "updateIconPayload",
//       "returnsField": "icon",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "icon",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "name",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteIcon",
//       "inputType": "deleteIconInput",
//       "payloadType": "deleteIconPayload",
//       "returnsField": "icon",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "ApiToken": {
//     "name": "ApiToken",
//     "queryItem": "apiToken",
//     "queryCollection": "apiTokens",
//     "collectionKind": "list",
//     "collectionType": "ApiToken",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "usuario",
//         "type": "Usuario!",
//         "namedType": "Usuario",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "expira",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "token",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "activo",
//         "type": "Boolean",
//         "namedType": "Boolean",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "createdAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "valid",
//         "type": "Boolean",
//         "namedType": "Boolean",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "expira",
//       "token",
//       "activo",
//       "_id",
//       "label",
//       "createdAt",
//       "updatedAt",
//       "valid"
//     ],
//     "relations": [
//       {
//         "name": "usuario",
//         "type": "Usuario!",
//         "namedType": "Usuario",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createApiToken",
//       "inputType": "createApiTokenInput",
//       "payloadType": "createApiTokenPayload",
//       "returnsField": "apiToken",
//       "inputFields": [
//         {
//           "name": "usuario",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "expira",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "token",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "activo",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateApiToken",
//       "inputType": "updateApiTokenInput",
//       "payloadType": "updateApiTokenPayload",
//       "returnsField": "apiToken",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "usuario",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "expira",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "token",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "activo",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteApiToken",
//       "inputType": "deleteApiTokenInput",
//       "payloadType": "deleteApiTokenPayload",
//       "returnsField": "apiToken",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Usuario": {
//     "name": "Usuario",
//     "queryItem": "usuario",
//     "queryCollection": "usuarios",
//     "collectionKind": "page-connection",
//     "collectionType": "UsuarioPageConnection",
//     "paginationType": "UsuarioPaginationInfo",
//     "orderInput": "UsuarioFilter_order",
//     "orderFields": [
//       "id",
//       "nombre",
//       "apellido",
//       "username",
//       "createdAt",
//       "email"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "createdAt",
//         "type": "[UsuarioFilter_createdAt]",
//         "namedType": "UsuarioFilter_createdAt",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "updatedAt",
//         "type": "[UsuarioFilter_updatedAt]",
//         "namedType": "UsuarioFilter_updatedAt",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "permisos_id",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "permisos_id_list",
//         "type": "[Int]",
//         "namedType": "Int",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "userRoles_id",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "userRoles_id_list",
//         "type": "[Int]",
//         "namedType": "Int",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "localidad_id",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "localidad_id_list",
//         "type": "[Int]",
//         "namedType": "Int",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "status_id",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "status_id_list",
//         "type": "[Int]",
//         "namedType": "Int",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "order",
//         "type": "[UsuarioFilter_order]",
//         "namedType": "UsuarioFilter_order",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "id",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "username",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "nombre",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "apellido",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "nit",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "email",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       }
//     ],
//     "filterArgs": [
//       {
//         "name": "createdAt",
//         "type": "[UsuarioFilter_createdAt]",
//         "namedType": "UsuarioFilter_createdAt",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "updatedAt",
//         "type": "[UsuarioFilter_updatedAt]",
//         "namedType": "UsuarioFilter_updatedAt",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "permisos_id",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "permisos_id_list",
//         "type": "[Int]",
//         "namedType": "Int",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "userRoles_id",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "userRoles_id_list",
//         "type": "[Int]",
//         "namedType": "Int",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "localidad_id",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "localidad_id_list",
//         "type": "[Int]",
//         "namedType": "Int",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "status_id",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "status_id_list",
//         "type": "[Int]",
//         "namedType": "Int",
//         "required": false,
//         "isList": true
//       },
//       {
//         "name": "id",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "username",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "nombre",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "apellido",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "nit",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "email",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       }
//     ],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "username",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "plainPassword",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "fullName",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "apiTokens",
//         "type": "[ApiToken]",
//         "namedType": "ApiToken",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "userRoles",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "permisos",
//         "type": "[Permiso]",
//         "namedType": "Permiso",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "directActions",
//         "type": "[Action]",
//         "namedType": "Action",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "deniedActions",
//         "type": "[Action]",
//         "namedType": "Action",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "ventas",
//         "type": "VentaPageConnection",
//         "namedType": "VentaPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "apellido",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "email",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nit",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "telefono",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "direccion",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "localidad",
//         "type": "Localidad",
//         "namedType": "Localidad",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "createdAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "token",
//         "type": "ApiToken",
//         "namedType": "ApiToken",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "username",
//       "plainPassword",
//       "fullName",
//       "nombre",
//       "apellido",
//       "email",
//       "nit",
//       "telefono",
//       "direccion",
//       "_id",
//       "label",
//       "createdAt",
//       "updatedAt"
//     ],
//     "relations": [
//       {
//         "name": "apiTokens",
//         "type": "[ApiToken]",
//         "namedType": "ApiToken",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "userRoles",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "permisos",
//         "type": "[Permiso]",
//         "namedType": "Permiso",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "directActions",
//         "type": "[Action]",
//         "namedType": "Action",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "deniedActions",
//         "type": "[Action]",
//         "namedType": "Action",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "localidad",
//         "type": "Localidad",
//         "namedType": "Localidad",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "token",
//         "type": "ApiToken",
//         "namedType": "ApiToken",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [
//       {
//         "name": "ventas",
//         "type": "VentaPageConnection",
//         "namedType": "VentaPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "create": {
//       "kind": "create",
//       "field": "createUsuario",
//       "inputType": "createUsuarioInput",
//       "payloadType": "createUsuarioPayload",
//       "returnsField": "usuario",
//       "inputFields": [
//         {
//           "name": "username",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "plainPassword",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "apiTokens",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "userRoles",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "permisos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "directActions",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "deniedActions",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "ventas",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "apellido",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "email",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "telefono",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "localidad",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateUsuario",
//       "inputType": "updateUsuarioInput",
//       "payloadType": "updateUsuarioPayload",
//       "returnsField": "usuario",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "username",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "plainPassword",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "apiTokens",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "userRoles",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "permisos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "directActions",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "deniedActions",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "ventas",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "apellido",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "email",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "telefono",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "localidad",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteUsuario",
//       "inputType": "deleteUsuarioInput",
//       "payloadType": "deleteUsuarioPayload",
//       "returnsField": "usuario",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Venta": {
//     "name": "Venta",
//     "queryItem": "venta",
//     "queryCollection": "ventas",
//     "collectionKind": "page-connection",
//     "collectionType": "VentaPageConnection",
//     "paginationType": "VentaPaginationInfo",
//     "orderInput": "VentaFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[VentaFilter_order]",
//         "namedType": "VentaFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "usuario",
//         "type": "Usuario!",
//         "namedType": "Usuario",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "boletos",
//         "type": "BoletoPageConnection",
//         "namedType": "BoletoPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "factura",
//         "type": "Factura",
//         "namedType": "Factura",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "enclave",
//         "type": "Enclave",
//         "namedType": "Enclave",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "empresa",
//         "type": "Empresa",
//         "namedType": "Empresa",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "createdAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "_id",
//       "label",
//       "createdAt",
//       "updatedAt"
//     ],
//     "relations": [
//       {
//         "name": "usuario",
//         "type": "Usuario!",
//         "namedType": "Usuario",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "factura",
//         "type": "Factura",
//         "namedType": "Factura",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "enclave",
//         "type": "Enclave",
//         "namedType": "Enclave",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "empresa",
//         "type": "Empresa",
//         "namedType": "Empresa",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [
//       {
//         "name": "boletos",
//         "type": "BoletoPageConnection",
//         "namedType": "BoletoPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "create": {
//       "kind": "create",
//       "field": "createVenta",
//       "inputType": "createVentaInput",
//       "payloadType": "createVentaPayload",
//       "returnsField": "venta",
//       "inputFields": [
//         {
//           "name": "usuario",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "boletos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "factura",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "enclave",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "empresa",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateVenta",
//       "inputType": "updateVentaInput",
//       "payloadType": "updateVentaPayload",
//       "returnsField": "venta",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "usuario",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "boletos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "factura",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "enclave",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "empresa",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteVenta",
//       "inputType": "deleteVentaInput",
//       "payloadType": "deleteVentaPayload",
//       "returnsField": "venta",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Boleto": {
//     "name": "Boleto",
//     "queryItem": "boleto",
//     "queryCollection": "boletos",
//     "collectionKind": "page-connection",
//     "collectionType": "BoletoPageConnection",
//     "paginationType": "BoletoPaginationInfo",
//     "orderInput": "BoletoFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[BoletoFilter_order]",
//         "namedType": "BoletoFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "recorrido",
//         "type": "Recorrido",
//         "namedType": "Recorrido",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "cliente",
//         "type": "Cliente!",
//         "namedType": "Cliente",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "legacyId",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "venta",
//         "type": "Venta!",
//         "namedType": "Venta",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "asiento",
//         "type": "Asiento!",
//         "namedType": "Asiento",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "servicio",
//         "type": "Servicio!",
//         "namedType": "Servicio",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "createdAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "legacyId",
//       "_id",
//       "label",
//       "createdAt",
//       "updatedAt"
//     ],
//     "relations": [
//       {
//         "name": "recorrido",
//         "type": "Recorrido",
//         "namedType": "Recorrido",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "cliente",
//         "type": "Cliente!",
//         "namedType": "Cliente",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "venta",
//         "type": "Venta!",
//         "namedType": "Venta",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "asiento",
//         "type": "Asiento!",
//         "namedType": "Asiento",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "servicio",
//         "type": "Servicio!",
//         "namedType": "Servicio",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createBoleto",
//       "inputType": "createBoletoInput",
//       "payloadType": "createBoletoPayload",
//       "returnsField": "boleto",
//       "inputFields": [
//         {
//           "name": "recorrido",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "cliente",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "legacyId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "venta",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "asiento",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "servicio",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateBoleto",
//       "inputType": "updateBoletoInput",
//       "payloadType": "updateBoletoPayload",
//       "returnsField": "boleto",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "recorrido",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "cliente",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "legacyId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "venta",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "asiento",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "servicio",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteBoleto",
//       "inputType": "deleteBoletoInput",
//       "payloadType": "deleteBoletoPayload",
//       "returnsField": "boleto",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Recorrido": {
//     "name": "Recorrido",
//     "queryItem": "recorrido",
//     "queryCollection": "recorridos",
//     "collectionKind": "page-connection",
//     "collectionType": "RecorridoPageConnection",
//     "paginationType": "RecorridoPaginationInfo",
//     "orderInput": "RecorridoFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[RecorridoFilter_order]",
//         "namedType": "RecorridoFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "empresa",
//         "type": "Empresa",
//         "namedType": "Empresa",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "bus",
//         "type": "Bus",
//         "namedType": "Bus",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "trayecto",
//         "type": "Trayecto",
//         "namedType": "Trayecto",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "subrecorridos",
//         "type": "RecorridoMatrioskaCursorConnection",
//         "namedType": "RecorridoMatrioskaCursorConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "nombre",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "empresa",
//         "type": "Empresa",
//         "namedType": "Empresa",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "bus",
//         "type": "Bus",
//         "namedType": "Bus",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "trayecto",
//         "type": "Trayecto",
//         "namedType": "Trayecto",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [
//       {
//         "name": "subrecorridos",
//         "type": "RecorridoMatrioskaCursorConnection",
//         "namedType": "RecorridoMatrioskaCursorConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "create": {
//       "kind": "create",
//       "field": "createRecorrido",
//       "inputType": "createRecorridoInput",
//       "payloadType": "createRecorridoPayload",
//       "returnsField": "recorrido",
//       "inputFields": [
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "empresa",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "bus",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "trayecto",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "subrecorridos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateRecorrido",
//       "inputType": "updateRecorridoInput",
//       "payloadType": "updateRecorridoPayload",
//       "returnsField": "recorrido",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "empresa",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "bus",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "trayecto",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "subrecorridos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteRecorrido",
//       "inputType": "deleteRecorridoInput",
//       "payloadType": "deleteRecorridoPayload",
//       "returnsField": "recorrido",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Empresa": {
//     "name": "Empresa",
//     "queryItem": "empresa",
//     "queryCollection": "empresas",
//     "collectionKind": "list",
//     "collectionType": "Empresa",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nit",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "direccion",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "telefono",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "email",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "buses",
//         "type": "BusPageConnection",
//         "namedType": "BusPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "ventas",
//         "type": "VentaPageConnection",
//         "namedType": "VentaPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "nombre",
//       "nit",
//       "direccion",
//       "telefono",
//       "email",
//       "_id",
//       "label"
//     ],
//     "relations": [],
//     "subcollections": [
//       {
//         "name": "buses",
//         "type": "BusPageConnection",
//         "namedType": "BusPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "ventas",
//         "type": "VentaPageConnection",
//         "namedType": "VentaPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "create": {
//       "kind": "create",
//       "field": "createEmpresa",
//       "inputType": "createEmpresaInput",
//       "payloadType": "createEmpresaPayload",
//       "returnsField": "empresa",
//       "inputFields": [
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "telefono",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "email",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "ventas",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateEmpresa",
//       "inputType": "updateEmpresaInput",
//       "payloadType": "updateEmpresaPayload",
//       "returnsField": "empresa",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "telefono",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "email",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "ventas",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteEmpresa",
//       "inputType": "deleteEmpresaInput",
//       "payloadType": "deleteEmpresaPayload",
//       "returnsField": "empresa",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Bus": {
//     "name": "Bus",
//     "queryItem": "bus",
//     "queryCollection": "buses",
//     "collectionKind": "page-connection",
//     "collectionType": "BusPageConnection",
//     "paginationType": "BusPaginationInfo",
//     "orderInput": "BusFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[BusFilter_order]",
//         "namedType": "BusFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "matricula",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "gama",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "empresa",
//         "type": "Empresa!",
//         "namedType": "Empresa",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "asientos",
//         "type": "[Asiento]",
//         "namedType": "Asiento",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "piloto",
//         "type": "Piloto",
//         "namedType": "Piloto",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "pilotoAux",
//         "type": "Piloto",
//         "namedType": "Piloto",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "marca",
//         "type": "BusMarca",
//         "namedType": "BusMarca",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "codigo",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "anoFabricacion",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "numeroSeguro",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "fechaVencimientoTarjetaOperaciones",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "numeroTarjetaRodaje",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "numeroTarjetaOperaciones",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "descripcion",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "matricula",
//       "gama",
//       "codigo",
//       "anoFabricacion",
//       "numeroSeguro",
//       "fechaVencimientoTarjetaOperaciones",
//       "numeroTarjetaRodaje",
//       "numeroTarjetaOperaciones",
//       "descripcion",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "empresa",
//         "type": "Empresa!",
//         "namedType": "Empresa",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "asientos",
//         "type": "[Asiento]",
//         "namedType": "Asiento",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "piloto",
//         "type": "Piloto",
//         "namedType": "Piloto",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "pilotoAux",
//         "type": "Piloto",
//         "namedType": "Piloto",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "marca",
//         "type": "BusMarca",
//         "namedType": "BusMarca",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createBus",
//       "inputType": "createBusInput",
//       "payloadType": "createBusPayload",
//       "returnsField": "bus",
//       "inputFields": [
//         {
//           "name": "matricula",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "gama",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "empresa",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "asientos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "piloto",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "pilotoAux",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "marca",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "codigo",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "anoFabricacion",
//           "type": "Int!",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "numeroSeguro",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "fechaVencimientoTarjetaOperaciones",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "numeroTarjetaRodaje",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "numeroTarjetaOperaciones",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "descripcion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateBus",
//       "inputType": "updateBusInput",
//       "payloadType": "updateBusPayload",
//       "returnsField": "bus",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "matricula",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "gama",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "empresa",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "asientos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "piloto",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "pilotoAux",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "marca",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "codigo",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "anoFabricacion",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "numeroSeguro",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "fechaVencimientoTarjetaOperaciones",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "numeroTarjetaRodaje",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "numeroTarjetaOperaciones",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "descripcion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteBus",
//       "inputType": "deleteBusInput",
//       "payloadType": "deleteBusPayload",
//       "returnsField": "bus",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Asiento": {
//     "name": "Asiento",
//     "queryItem": "asiento",
//     "queryCollection": "asientos",
//     "collectionKind": "list",
//     "collectionType": "Asiento",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "numero",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "clase",
//         "type": "TipoAsiento!",
//         "namedType": "TipoAsiento",
//         "kind": "ENUM",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "fila",
//         "type": "Int",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "columna",
//         "type": "Int",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "bus",
//         "type": "Bus!",
//         "namedType": "Bus",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "numero",
//       "clase",
//       "fila",
//       "columna",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "bus",
//         "type": "Bus!",
//         "namedType": "Bus",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createAsiento",
//       "inputType": "createAsientoInput",
//       "payloadType": "createAsientoPayload",
//       "returnsField": "asiento",
//       "inputFields": [
//         {
//           "name": "numero",
//           "type": "Int!",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clase",
//           "type": "TipoAsiento!",
//           "namedType": "TipoAsiento",
//           "kind": "ENUM",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "fila",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "columna",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "bus",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateAsiento",
//       "inputType": "updateAsientoInput",
//       "payloadType": "updateAsientoPayload",
//       "returnsField": "asiento",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "numero",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clase",
//           "type": "TipoAsiento",
//           "namedType": "TipoAsiento",
//           "kind": "ENUM",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "fila",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "columna",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "bus",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteAsiento",
//       "inputType": "deleteAsientoInput",
//       "payloadType": "deleteAsientoPayload",
//       "returnsField": "asiento",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Piloto": {
//     "name": "Piloto",
//     "queryItem": "piloto",
//     "queryCollection": "pilotos",
//     "collectionKind": "page-connection",
//     "collectionType": "PilotoPageConnection",
//     "paginationType": "PilotoPaginationInfo",
//     "orderInput": "PilotoFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[PilotoFilter_order]",
//         "namedType": "PilotoFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "fechaNacimiento",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "numeroLicencia",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "fechaVencimientoLicencia",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "dpi",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "seguroSocial",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "codigo",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "empresa",
//         "type": "Empresa",
//         "namedType": "Empresa",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "apellido",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "email",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nit",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "telefono",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "direccion",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "localidad",
//         "type": "Localidad",
//         "namedType": "Localidad",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "createdAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "fechaNacimiento",
//       "numeroLicencia",
//       "fechaVencimientoLicencia",
//       "dpi",
//       "seguroSocial",
//       "codigo",
//       "nombre",
//       "apellido",
//       "email",
//       "nit",
//       "telefono",
//       "direccion",
//       "_id",
//       "label",
//       "createdAt",
//       "updatedAt"
//     ],
//     "relations": [
//       {
//         "name": "empresa",
//         "type": "Empresa",
//         "namedType": "Empresa",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "localidad",
//         "type": "Localidad",
//         "namedType": "Localidad",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createPiloto",
//       "inputType": "createPilotoInput",
//       "payloadType": "createPilotoPayload",
//       "returnsField": "piloto",
//       "inputFields": [
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "apellido",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "email",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "telefono",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "localidad",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updatePiloto",
//       "inputType": "updatePilotoInput",
//       "payloadType": "updatePilotoPayload",
//       "returnsField": "piloto",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "apellido",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "email",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "telefono",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "localidad",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deletePiloto",
//       "inputType": "deletePilotoInput",
//       "payloadType": "deletePilotoPayload",
//       "returnsField": "piloto",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Localidad": {
//     "name": "Localidad",
//     "queryItem": "localidad",
//     "queryCollection": "localidads",
//     "collectionKind": "page-connection",
//     "collectionType": "LocalidadPageConnection",
//     "paginationType": "LocalidadPaginationInfo",
//     "orderInput": "LocalidadFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[LocalidadFilter_order]",
//         "namedType": "LocalidadFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nacion",
//         "type": "Nacion",
//         "namedType": "Nacion",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "nombre",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "nacion",
//         "type": "Nacion",
//         "namedType": "Nacion",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createLocalidad",
//       "inputType": "createLocalidadInput",
//       "payloadType": "createLocalidadPayload",
//       "returnsField": "localidad",
//       "inputFields": [
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nacion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateLocalidad",
//       "inputType": "updateLocalidadInput",
//       "payloadType": "updateLocalidadPayload",
//       "returnsField": "localidad",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nacion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteLocalidad",
//       "inputType": "deleteLocalidadInput",
//       "payloadType": "deleteLocalidadPayload",
//       "returnsField": "localidad",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Nacion": {
//     "name": "Nacion",
//     "queryItem": "nacion",
//     "queryCollection": "nacions",
//     "collectionKind": "page-connection",
//     "collectionType": "NacionPageConnection",
//     "paginationType": "NacionPaginationInfo",
//     "orderInput": "NacionFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[NacionFilter_order]",
//         "namedType": "NacionFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "nombre",
//       "_id",
//       "label"
//     ],
//     "relations": [],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createNacion",
//       "inputType": "createNacionInput",
//       "payloadType": "createNacionPayload",
//       "returnsField": "nacion",
//       "inputFields": [
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateNacion",
//       "inputType": "updateNacionInput",
//       "payloadType": "updateNacionPayload",
//       "returnsField": "nacion",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteNacion",
//       "inputType": "deleteNacionInput",
//       "payloadType": "deleteNacionPayload",
//       "returnsField": "nacion",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "BusMarca": {
//     "name": "BusMarca",
//     "queryItem": "busMarca",
//     "queryCollection": "busMarcas",
//     "collectionKind": "list",
//     "collectionType": "BusMarca",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "nombre",
//       "_id",
//       "label"
//     ],
//     "relations": [],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createBusMarca",
//       "inputType": "createBusMarcaInput",
//       "payloadType": "createBusMarcaPayload",
//       "returnsField": "busMarca",
//       "inputFields": [
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateBusMarca",
//       "inputType": "updateBusMarcaInput",
//       "payloadType": "updateBusMarcaPayload",
//       "returnsField": "busMarca",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteBusMarca",
//       "inputType": "deleteBusMarcaInput",
//       "payloadType": "deleteBusMarcaPayload",
//       "returnsField": "busMarca",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Trayecto": {
//     "name": "Trayecto",
//     "queryItem": "trayecto",
//     "queryCollection": "trayectos",
//     "collectionKind": "page-connection",
//     "collectionType": "TrayectoPageConnection",
//     "paginationType": "TrayectoPaginationInfo",
//     "orderInput": "TrayectoFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[TrayectoFilter_order]",
//         "namedType": "TrayectoFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "origen",
//         "type": "Enclave!",
//         "namedType": "Enclave",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "destino",
//         "type": "Enclave!",
//         "namedType": "Enclave",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "distanciaKm",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "duracionEstimadaMinutos",
//         "type": "Int",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "activo",
//         "type": "Boolean!",
//         "namedType": "Boolean",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "legacyId",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "trayectosHijos",
//         "type": "TrayectoPageConnection",
//         "namedType": "TrayectoPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "trayectosPadres",
//         "type": "TrayectoPageConnection",
//         "namedType": "TrayectoPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "recorridos",
//         "type": "RecorridoPageConnection",
//         "namedType": "RecorridoPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "distanciaKm",
//       "duracionEstimadaMinutos",
//       "activo",
//       "legacyId",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "origen",
//         "type": "Enclave!",
//         "namedType": "Enclave",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "destino",
//         "type": "Enclave!",
//         "namedType": "Enclave",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [
//       {
//         "name": "trayectosHijos",
//         "type": "TrayectoPageConnection",
//         "namedType": "TrayectoPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "trayectosPadres",
//         "type": "TrayectoPageConnection",
//         "namedType": "TrayectoPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "recorridos",
//         "type": "RecorridoPageConnection",
//         "namedType": "RecorridoPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "create": {
//       "kind": "create",
//       "field": "createTrayecto",
//       "inputType": "createTrayectoInput",
//       "payloadType": "createTrayectoPayload",
//       "returnsField": "trayecto",
//       "inputFields": [
//         {
//           "name": "origen",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "destino",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "distanciaKm",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "duracionEstimadaMinutos",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "activo",
//           "type": "Boolean!",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "legacyId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "recorridos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateTrayecto",
//       "inputType": "updateTrayectoInput",
//       "payloadType": "updateTrayectoPayload",
//       "returnsField": "trayecto",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "origen",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "destino",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "distanciaKm",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "duracionEstimadaMinutos",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "activo",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "legacyId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "recorridos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteTrayecto",
//       "inputType": "deleteTrayectoInput",
//       "payloadType": "deleteTrayectoPayload",
//       "returnsField": "trayecto",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Enclave": {
//     "name": "Enclave",
//     "queryItem": "enclave",
//     "queryCollection": "enclaves",
//     "collectionKind": "page-connection",
//     "collectionType": "EnclavePageConnection",
//     "paginationType": "EnclavePaginationInfo",
//     "orderInput": "EnclaveFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[EnclaveFilter_order]",
//         "namedType": "EnclaveFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "direccion",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "latitud",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "longitud",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "ventas",
//         "type": "VentaPageConnection",
//         "namedType": "VentaPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "nombre",
//       "direccion",
//       "latitud",
//       "longitud",
//       "_id",
//       "label"
//     ],
//     "relations": [],
//     "subcollections": [
//       {
//         "name": "ventas",
//         "type": "VentaPageConnection",
//         "namedType": "VentaPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "create": {
//       "kind": "create",
//       "field": "createEnclave",
//       "inputType": "createEnclaveInput",
//       "payloadType": "createEnclavePayload",
//       "returnsField": "enclave",
//       "inputFields": [
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "latitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "longitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "ventas",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateEnclave",
//       "inputType": "updateEnclaveInput",
//       "payloadType": "updateEnclavePayload",
//       "returnsField": "enclave",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "latitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "longitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "ventas",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteEnclave",
//       "inputType": "deleteEnclaveInput",
//       "payloadType": "deleteEnclavePayload",
//       "returnsField": "enclave",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "RecorridoMatrioska": {
//     "name": "RecorridoMatrioska",
//     "queryItem": "recorridoMatrioska",
//     "queryCollection": "recorridoMatrioskas",
//     "collectionKind": "cursor-connection",
//     "collectionType": "RecorridoMatrioskaCursorConnection",
//     "paginationType": "RecorridoMatrioskaPageInfo",
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "first",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "last",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "before",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "after",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "recorrido",
//         "type": "Recorrido!",
//         "namedType": "Recorrido",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "subrecorridos",
//         "type": "Recorrido!",
//         "namedType": "Recorrido",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "posicion",
//         "type": "Int",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "_id",
//       "posicion"
//     ],
//     "relations": [
//       {
//         "name": "recorrido",
//         "type": "Recorrido!",
//         "namedType": "Recorrido",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "subrecorridos",
//         "type": "Recorrido!",
//         "namedType": "Recorrido",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createRecorridoMatrioska",
//       "inputType": "createRecorridoMatrioskaInput",
//       "payloadType": "createRecorridoMatrioskaPayload",
//       "returnsField": "recorridoMatrioska",
//       "inputFields": [
//         {
//           "name": "recorrido",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "subrecorridos",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "posicion",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateRecorridoMatrioska",
//       "inputType": "updateRecorridoMatrioskaInput",
//       "payloadType": "updateRecorridoMatrioskaPayload",
//       "returnsField": "recorridoMatrioska",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "recorrido",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "subrecorridos",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "posicion",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteRecorridoMatrioska",
//       "inputType": "deleteRecorridoMatrioskaInput",
//       "payloadType": "deleteRecorridoMatrioskaPayload",
//       "returnsField": "recorridoMatrioska",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Cliente": {
//     "name": "Cliente",
//     "queryItem": "cliente",
//     "queryCollection": "clientes",
//     "collectionKind": "page-connection",
//     "collectionType": "ClientePageConnection",
//     "paginationType": "ClientePaginationInfo",
//     "orderInput": "ClienteFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[ClienteFilter_order]",
//         "namedType": "ClienteFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "apellido",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "email",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nit",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "telefono",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "direccion",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "localidad",
//         "type": "Localidad",
//         "namedType": "Localidad",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "createdAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "nombre",
//       "apellido",
//       "email",
//       "nit",
//       "telefono",
//       "direccion",
//       "_id",
//       "label",
//       "createdAt",
//       "updatedAt"
//     ],
//     "relations": [
//       {
//         "name": "localidad",
//         "type": "Localidad",
//         "namedType": "Localidad",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createCliente",
//       "inputType": "createClienteInput",
//       "payloadType": "createClientePayload",
//       "returnsField": "cliente",
//       "inputFields": [
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "apellido",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "email",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "telefono",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "localidad",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateCliente",
//       "inputType": "updateClienteInput",
//       "payloadType": "updateClientePayload",
//       "returnsField": "cliente",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "apellido",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "email",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "telefono",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "localidad",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteCliente",
//       "inputType": "deleteClienteInput",
//       "payloadType": "deleteClientePayload",
//       "returnsField": "cliente",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Servicio": {
//     "name": "Servicio",
//     "queryItem": "servicio",
//     "queryCollection": "servicios",
//     "collectionKind": "page-connection",
//     "collectionType": "ServicioPageConnection",
//     "paginationType": "ServicioPaginationInfo",
//     "orderInput": "ServicioFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[ServicioFilter_order]",
//         "namedType": "ServicioFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "fecha",
//         "type": "Date!",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "legacyId",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "empresa",
//         "type": "Empresa",
//         "namedType": "Empresa",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "recorrido",
//         "type": "Recorrido!",
//         "namedType": "Recorrido",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "bus",
//         "type": "Bus",
//         "namedType": "Bus",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "piloto",
//         "type": "Piloto",
//         "namedType": "Piloto",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "boletos",
//         "type": "BoletoPageConnection",
//         "namedType": "BoletoPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "createdAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "fecha",
//       "legacyId",
//       "_id",
//       "label",
//       "createdAt",
//       "updatedAt"
//     ],
//     "relations": [
//       {
//         "name": "empresa",
//         "type": "Empresa",
//         "namedType": "Empresa",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "recorrido",
//         "type": "Recorrido!",
//         "namedType": "Recorrido",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "bus",
//         "type": "Bus",
//         "namedType": "Bus",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "piloto",
//         "type": "Piloto",
//         "namedType": "Piloto",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [
//       {
//         "name": "boletos",
//         "type": "BoletoPageConnection",
//         "namedType": "BoletoPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "create": {
//       "kind": "create",
//       "field": "createServicio",
//       "inputType": "createServicioInput",
//       "payloadType": "createServicioPayload",
//       "returnsField": "servicio",
//       "inputFields": [
//         {
//           "name": "fecha",
//           "type": "Date!",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "legacyId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "empresa",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "recorrido",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "bus",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "piloto",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "boletos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateServicio",
//       "inputType": "updateServicioInput",
//       "payloadType": "updateServicioPayload",
//       "returnsField": "servicio",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "fecha",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "legacyId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "empresa",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "recorrido",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "bus",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "piloto",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "boletos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteServicio",
//       "inputType": "deleteServicioInput",
//       "payloadType": "deleteServicioPayload",
//       "returnsField": "servicio",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Factura": {
//     "name": "Factura",
//     "queryItem": "factura",
//     "queryCollection": "facturas",
//     "collectionKind": "cursor-connection",
//     "collectionType": "FacturaCursorConnection",
//     "paginationType": "FacturaPageInfo",
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "first",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "last",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "before",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "after",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "dte",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "serie",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "fecha",
//         "type": "Date!",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "emisorNit",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "emisorNombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "establecimientoCodigo",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "emisorNombreComercial",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "receptopNit",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "receptorNombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "createdAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "_id",
//       "dte",
//       "serie",
//       "fecha",
//       "emisorNit",
//       "emisorNombre",
//       "establecimientoCodigo",
//       "emisorNombreComercial",
//       "receptopNit",
//       "receptorNombre",
//       "createdAt",
//       "updatedAt"
//     ],
//     "relations": [],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createFactura",
//       "inputType": "createFacturaInput",
//       "payloadType": "createFacturaPayload",
//       "returnsField": "factura",
//       "inputFields": [
//         {
//           "name": "dte",
//           "type": "Int!",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "serie",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "fecha",
//           "type": "Date!",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "emisorNit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "emisorNombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "establecimientoCodigo",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "emisorNombreComercial",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "receptopNit",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "receptorNombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateFactura",
//       "inputType": "updateFacturaInput",
//       "payloadType": "updateFacturaPayload",
//       "returnsField": "factura",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "dte",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "serie",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "fecha",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "emisorNit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "emisorNombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "establecimientoCodigo",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "emisorNombreComercial",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "receptopNit",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "receptorNombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteFactura",
//       "inputType": "deleteFacturaInput",
//       "payloadType": "deleteFacturaPayload",
//       "returnsField": "factura",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "CollectionFieldConfig": {
//     "name": "CollectionFieldConfig",
//     "queryItem": "collectionFieldConfig",
//     "queryCollection": "collectionFieldConfigs",
//     "collectionKind": "list",
//     "collectionType": "CollectionFieldConfig",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "entityConfig",
//         "type": "EntityConfiguration",
//         "namedType": "EntityConfiguration",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "sortable",
//         "type": "Boolean",
//         "namedType": "Boolean",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "filterable",
//         "type": "Boolean",
//         "namedType": "Boolean",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "field",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "position",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "visible",
//         "type": "Boolean",
//         "namedType": "Boolean",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "attrs",
//         "type": "Iterable",
//         "namedType": "Iterable",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "name",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "sortable",
//       "filterable",
//       "_id",
//       "field",
//       "position",
//       "visible",
//       "label",
//       "attrs",
//       "name"
//     ],
//     "relations": [
//       {
//         "name": "entityConfig",
//         "type": "EntityConfiguration",
//         "namedType": "EntityConfiguration",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createCollectionFieldConfig",
//       "inputType": "createCollectionFieldConfigInput",
//       "payloadType": "createCollectionFieldConfigPayload",
//       "returnsField": "collectionFieldConfig",
//       "inputFields": [
//         {
//           "name": "entityConfig",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "sortable",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "filterable",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "field",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "position",
//           "type": "Int!",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "visible",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "attrs",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateCollectionFieldConfig",
//       "inputType": "updateCollectionFieldConfigInput",
//       "payloadType": "updateCollectionFieldConfigPayload",
//       "returnsField": "collectionFieldConfig",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "entityConfig",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "sortable",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "filterable",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "field",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "position",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "visible",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "attrs",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteCollectionFieldConfig",
//       "inputType": "deleteCollectionFieldConfigInput",
//       "payloadType": "deleteCollectionFieldConfigPayload",
//       "returnsField": "collectionFieldConfig",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "EntityConfiguration": {
//     "name": "EntityConfiguration",
//     "queryItem": "entityConfiguration",
//     "queryCollection": "entityConfigurations",
//     "collectionKind": "list",
//     "collectionType": "EntityConfiguration",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "entityClass",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       }
//     ],
//     "filterArgs": [
//       {
//         "name": "entityClass",
//         "type": "String",
//         "namedType": "String",
//         "required": false,
//         "isList": false
//       }
//     ],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "entityClass",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "collectionFieldConfig",
//         "type": "[CollectionFieldConfig]",
//         "namedType": "CollectionFieldConfig",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "formFields",
//         "type": "[FormFieldConfig]",
//         "namedType": "FormFieldConfig",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "icon",
//         "type": "Icon",
//         "namedType": "Icon",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "_id",
//       "entityClass",
//       "updatedAt"
//     ],
//     "relations": [
//       {
//         "name": "collectionFieldConfig",
//         "type": "[CollectionFieldConfig]",
//         "namedType": "CollectionFieldConfig",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "formFields",
//         "type": "[FormFieldConfig]",
//         "namedType": "FormFieldConfig",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "icon",
//         "type": "Icon",
//         "namedType": "Icon",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": null,
//     "update": {
//       "kind": "update",
//       "field": "updateEntityConfiguration",
//       "inputType": "updateEntityConfigurationInput",
//       "payloadType": "updateEntityConfigurationPayload",
//       "returnsField": "entityConfiguration",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "entityClass",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "collectionFieldConfig",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "formFields",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "icon",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": null
//   },
//   "FormFieldConfig": {
//     "name": "FormFieldConfig",
//     "queryItem": "formFieldConfig",
//     "queryCollection": "formFieldConfigs",
//     "collectionKind": "list",
//     "collectionType": "FormFieldConfig",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "groupName",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "entityConfig",
//         "type": "EntityConfiguration",
//         "namedType": "EntityConfiguration",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "field",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "position",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "visible",
//         "type": "Boolean",
//         "namedType": "Boolean",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "attrs",
//         "type": "Iterable",
//         "namedType": "Iterable",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "name",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "groupName",
//       "_id",
//       "field",
//       "position",
//       "visible",
//       "label",
//       "attrs",
//       "name"
//     ],
//     "relations": [
//       {
//         "name": "entityConfig",
//         "type": "EntityConfiguration",
//         "namedType": "EntityConfiguration",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createFormFieldConfig",
//       "inputType": "createFormFieldConfigInput",
//       "payloadType": "createFormFieldConfigPayload",
//       "returnsField": "formFieldConfig",
//       "inputFields": [
//         {
//           "name": "groupName",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "entityConfig",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "field",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "position",
//           "type": "Int!",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "visible",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "attrs",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "data",
//           "type": "Iterable!",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateFormFieldConfig",
//       "inputType": "updateFormFieldConfigInput",
//       "payloadType": "updateFormFieldConfigPayload",
//       "returnsField": "formFieldConfig",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "groupName",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "entityConfig",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "field",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "position",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "visible",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "attrs",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "data",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteFormFieldConfig",
//       "inputType": "deleteFormFieldConfigInput",
//       "payloadType": "deleteFormFieldConfigPayload",
//       "returnsField": "formFieldConfig",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Estacion": {
//     "name": "Estacion",
//     "queryItem": "estacion",
//     "queryCollection": "estacions",
//     "collectionKind": "page-connection",
//     "collectionType": "EstacionPageConnection",
//     "paginationType": "EstacionPaginationInfo",
//     "orderInput": "EstacionFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[EstacionFilter_order]",
//         "namedType": "EstacionFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "direccion",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "latitud",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "longitud",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "ventas",
//         "type": "VentaPageConnection",
//         "namedType": "VentaPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "_id",
//       "label",
//       "nombre",
//       "direccion",
//       "latitud",
//       "longitud"
//     ],
//     "relations": [],
//     "subcollections": [
//       {
//         "name": "ventas",
//         "type": "VentaPageConnection",
//         "namedType": "VentaPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "create": {
//       "kind": "create",
//       "field": "createEstacion",
//       "inputType": "createEstacionInput",
//       "payloadType": "createEstacionPayload",
//       "returnsField": "estacion",
//       "inputFields": [
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "latitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "longitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "ventas",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateEstacion",
//       "inputType": "updateEstacionInput",
//       "payloadType": "updateEstacionPayload",
//       "returnsField": "estacion",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "latitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "longitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "ventas",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteEstacion",
//       "inputType": "deleteEstacionInput",
//       "payloadType": "deleteEstacionPayload",
//       "returnsField": "estacion",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "LayoutProfile": {
//     "name": "LayoutProfile",
//     "queryItem": "layoutProfile",
//     "queryCollection": "layoutProfiles",
//     "collectionKind": "list",
//     "collectionType": "LayoutProfile",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "enabled",
//         "type": "Boolean!",
//         "namedType": "Boolean",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "layoutSchema",
//         "type": "LayoutSchema!",
//         "namedType": "LayoutSchema",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "roleAssignments",
//         "type": "[LayoutProfileRole]",
//         "namedType": "LayoutProfileRole",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "usuarioAssignments",
//         "type": "[LayoutProfileUsuario]",
//         "namedType": "LayoutProfileUsuario",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nota",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "createdAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "enabled",
//       "nombre",
//       "nota",
//       "_id",
//       "label",
//       "createdAt",
//       "updatedAt"
//     ],
//     "relations": [
//       {
//         "name": "layoutSchema",
//         "type": "LayoutSchema!",
//         "namedType": "LayoutSchema",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "roleAssignments",
//         "type": "[LayoutProfileRole]",
//         "namedType": "LayoutProfileRole",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "usuarioAssignments",
//         "type": "[LayoutProfileUsuario]",
//         "namedType": "LayoutProfileUsuario",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createLayoutProfile",
//       "inputType": "createLayoutProfileInput",
//       "payloadType": "createLayoutProfilePayload",
//       "returnsField": "layoutProfile",
//       "inputFields": [
//         {
//           "name": "enabled",
//           "type": "Boolean!",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "layoutSchema",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "roleAssignments",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "usuarioAssignments",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nota",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateLayoutProfile",
//       "inputType": "updateLayoutProfileInput",
//       "payloadType": "updateLayoutProfilePayload",
//       "returnsField": "layoutProfile",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "enabled",
//           "type": "Boolean",
//           "namedType": "Boolean",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "layoutSchema",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "roleAssignments",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "usuarioAssignments",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nota",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteLayoutProfile",
//       "inputType": "deleteLayoutProfileInput",
//       "payloadType": "deleteLayoutProfilePayload",
//       "returnsField": "layoutProfile",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "LayoutSchema": {
//     "name": "LayoutSchema",
//     "queryItem": "layoutSchema",
//     "queryCollection": "layoutSchemas",
//     "collectionKind": "list",
//     "collectionType": "LayoutSchema",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "area",
//         "type": "LayoutArea!",
//         "namedType": "LayoutArea",
//         "kind": "ENUM",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "items",
//         "type": "[LayoutSchemaItem]",
//         "namedType": "LayoutSchemaItem",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nota",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "createdAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "updatedAt",
//         "type": "Date",
//         "namedType": "Date",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "area",
//       "nombre",
//       "nota",
//       "_id",
//       "label",
//       "createdAt",
//       "updatedAt"
//     ],
//     "relations": [
//       {
//         "name": "items",
//         "type": "[LayoutSchemaItem]",
//         "namedType": "LayoutSchemaItem",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "status",
//         "type": "Status",
//         "namedType": "Status",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createLayoutSchema",
//       "inputType": "createLayoutSchemaInput",
//       "payloadType": "createLayoutSchemaPayload",
//       "returnsField": "layoutSchema",
//       "inputFields": [
//         {
//           "name": "area",
//           "type": "LayoutArea!",
//           "namedType": "LayoutArea",
//           "kind": "ENUM",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "items",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nota",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateLayoutSchema",
//       "inputType": "updateLayoutSchemaInput",
//       "payloadType": "updateLayoutSchemaPayload",
//       "returnsField": "layoutSchema",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "area",
//           "type": "LayoutArea",
//           "namedType": "LayoutArea",
//           "kind": "ENUM",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "items",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nota",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "status",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "createdAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "updatedAt",
//           "type": "Date",
//           "namedType": "Date",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteLayoutSchema",
//       "inputType": "deleteLayoutSchemaInput",
//       "payloadType": "deleteLayoutSchemaPayload",
//       "returnsField": "layoutSchema",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "LayoutSchemaItem": {
//     "name": "LayoutSchemaItem",
//     "queryItem": "layoutSchemaItem",
//     "queryCollection": "layoutSchemaItems",
//     "collectionKind": "list",
//     "collectionType": "LayoutSchemaItem",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "layoutSchema",
//         "type": "LayoutSchema!",
//         "namedType": "LayoutSchema",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "vueRoute",
//         "type": "VueRoute!",
//         "namedType": "VueRoute",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "position",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "position",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "layoutSchema",
//         "type": "LayoutSchema!",
//         "namedType": "LayoutSchema",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "vueRoute",
//         "type": "VueRoute!",
//         "namedType": "VueRoute",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createLayoutSchemaItem",
//       "inputType": "createLayoutSchemaItemInput",
//       "payloadType": "createLayoutSchemaItemPayload",
//       "returnsField": "layoutSchemaItem",
//       "inputFields": [
//         {
//           "name": "layoutSchema",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "vueRoute",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "position",
//           "type": "Int!",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateLayoutSchemaItem",
//       "inputType": "updateLayoutSchemaItemInput",
//       "payloadType": "updateLayoutSchemaItemPayload",
//       "returnsField": "layoutSchemaItem",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "layoutSchema",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "vueRoute",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "position",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteLayoutSchemaItem",
//       "inputType": "deleteLayoutSchemaItemInput",
//       "payloadType": "deleteLayoutSchemaItemPayload",
//       "returnsField": "layoutSchemaItem",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "VueRoute": {
//     "name": "VueRoute",
//     "queryItem": "vueRoute",
//     "queryCollection": "vueRoutes",
//     "collectionKind": "list",
//     "collectionType": "VueRoute",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "vueRouteName",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "path",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "params",
//         "type": "Iterable",
//         "namedType": "Iterable",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "icon",
//         "type": "Icon",
//         "namedType": "Icon",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "roles",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "usuariosPermitidos",
//         "type": "UsuarioPageConnection",
//         "namedType": "UsuarioPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "usuariosDenegados",
//         "type": "UsuarioPageConnection",
//         "namedType": "UsuarioPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "vueRoute",
//         "type": "VueRoute",
//         "namedType": "VueRoute",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "hijos",
//         "type": "[VueRoute]",
//         "namedType": "VueRoute",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "_id",
//       "nombre",
//       "vueRouteName",
//       "path",
//       "params"
//     ],
//     "relations": [
//       {
//         "name": "icon",
//         "type": "Icon",
//         "namedType": "Icon",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "roles",
//         "type": "[Role]",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "vueRoute",
//         "type": "VueRoute",
//         "namedType": "VueRoute",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "hijos",
//         "type": "[VueRoute]",
//         "namedType": "VueRoute",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": true,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [
//       {
//         "name": "usuariosPermitidos",
//         "type": "UsuarioPageConnection",
//         "namedType": "UsuarioPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       },
//       {
//         "name": "usuariosDenegados",
//         "type": "UsuarioPageConnection",
//         "namedType": "UsuarioPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "create": {
//       "kind": "create",
//       "field": "createVueRoute",
//       "inputType": "createVueRouteInput",
//       "payloadType": "createVueRoutePayload",
//       "returnsField": "vueRoute",
//       "inputFields": [
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "vueRouteName",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "path",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "params",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "icon",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "roles",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "usuariosPermitidos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "usuariosDenegados",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "vueRoute",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "hijos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateVueRoute",
//       "inputType": "updateVueRouteInput",
//       "payloadType": "updateVueRoutePayload",
//       "returnsField": "vueRoute",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "vueRouteName",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "path",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "params",
//           "type": "Iterable",
//           "namedType": "Iterable",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "icon",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "roles",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "usuariosPermitidos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "usuariosDenegados",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "vueRoute",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "hijos",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteVueRoute",
//       "inputType": "deleteVueRouteInput",
//       "payloadType": "deleteVueRoutePayload",
//       "returnsField": "vueRoute",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "LayoutProfileRole": {
//     "name": "LayoutProfileRole",
//     "queryItem": "layoutProfileRole",
//     "queryCollection": "layoutProfileRoles",
//     "collectionKind": "list",
//     "collectionType": "LayoutProfileRole",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "layoutProfile",
//         "type": "LayoutProfile!",
//         "namedType": "LayoutProfile",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "role",
//         "type": "Role!",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "position",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "position",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "layoutProfile",
//         "type": "LayoutProfile!",
//         "namedType": "LayoutProfile",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "role",
//         "type": "Role!",
//         "namedType": "Role",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createLayoutProfileRole",
//       "inputType": "createLayoutProfileRoleInput",
//       "payloadType": "createLayoutProfileRolePayload",
//       "returnsField": "layoutProfileRole",
//       "inputFields": [
//         {
//           "name": "layoutProfile",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "role",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "position",
//           "type": "Int!",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateLayoutProfileRole",
//       "inputType": "updateLayoutProfileRoleInput",
//       "payloadType": "updateLayoutProfileRolePayload",
//       "returnsField": "layoutProfileRole",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "layoutProfile",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "role",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "position",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteLayoutProfileRole",
//       "inputType": "deleteLayoutProfileRoleInput",
//       "payloadType": "deleteLayoutProfileRolePayload",
//       "returnsField": "layoutProfileRole",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "LayoutProfileUsuario": {
//     "name": "LayoutProfileUsuario",
//     "queryItem": "layoutProfileUsuario",
//     "queryCollection": "layoutProfileUsuarios",
//     "collectionKind": "list",
//     "collectionType": "LayoutProfileUsuario",
//     "paginationType": null,
//     "orderInput": null,
//     "orderFields": [],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "layoutProfile",
//         "type": "LayoutProfile!",
//         "namedType": "LayoutProfile",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "usuario",
//         "type": "Usuario!",
//         "namedType": "Usuario",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "position",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "position",
//       "_id",
//       "label"
//     ],
//     "relations": [
//       {
//         "name": "layoutProfile",
//         "type": "LayoutProfile!",
//         "namedType": "LayoutProfile",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       },
//       {
//         "name": "usuario",
//         "type": "Usuario!",
//         "namedType": "Usuario",
//         "kind": "OBJECT",
//         "required": true,
//         "isList": false,
//         "isRelation": true,
//         "isSubcollection": false
//       }
//     ],
//     "subcollections": [],
//     "create": {
//       "kind": "create",
//       "field": "createLayoutProfileUsuario",
//       "inputType": "createLayoutProfileUsuarioInput",
//       "payloadType": "createLayoutProfileUsuarioPayload",
//       "returnsField": "layoutProfileUsuario",
//       "inputFields": [
//         {
//           "name": "layoutProfile",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "usuario",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "position",
//           "type": "Int!",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateLayoutProfileUsuario",
//       "inputType": "updateLayoutProfileUsuarioInput",
//       "payloadType": "updateLayoutProfileUsuarioPayload",
//       "returnsField": "layoutProfileUsuario",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "layoutProfile",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "usuario",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "position",
//           "type": "Int",
//           "namedType": "Int",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteLayoutProfileUsuario",
//       "inputType": "deleteLayoutProfileUsuarioInput",
//       "payloadType": "deleteLayoutProfileUsuarioPayload",
//       "returnsField": "layoutProfileUsuario",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   },
//   "Parada": {
//     "name": "Parada",
//     "queryItem": "parada",
//     "queryCollection": "paradas",
//     "collectionKind": "page-connection",
//     "collectionType": "ParadaPageConnection",
//     "paginationType": "ParadaPaginationInfo",
//     "orderInput": "ParadaFilter_order",
//     "orderFields": [
//       "id"
//     ],
//     "itemArgs": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "required": true,
//         "isList": false
//       }
//     ],
//     "collectionArgs": [
//       {
//         "name": "currentPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "itemsPerPage",
//         "type": "Int",
//         "namedType": "Int",
//         "required": false,
//         "isList": false
//       },
//       {
//         "name": "order",
//         "type": "[ParadaFilter_order]",
//         "namedType": "ParadaFilter_order",
//         "required": false,
//         "isList": true
//       }
//     ],
//     "filterArgs": [],
//     "fields": [
//       {
//         "name": "id",
//         "type": "ID!",
//         "namedType": "ID",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "_id",
//         "type": "Int!",
//         "namedType": "Int",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "label",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "nombre",
//         "type": "String!",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": true,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "direccion",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "latitud",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "longitud",
//         "type": "String",
//         "namedType": "String",
//         "kind": "SCALAR",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": false
//       },
//       {
//         "name": "ventas",
//         "type": "VentaPageConnection",
//         "namedType": "VentaPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "scalarFields": [
//       "id",
//       "_id",
//       "label",
//       "nombre",
//       "direccion",
//       "latitud",
//       "longitud"
//     ],
//     "relations": [],
//     "subcollections": [
//       {
//         "name": "ventas",
//         "type": "VentaPageConnection",
//         "namedType": "VentaPageConnection",
//         "kind": "OBJECT",
//         "required": false,
//         "isList": false,
//         "isRelation": false,
//         "isSubcollection": true
//       }
//     ],
//     "create": {
//       "kind": "create",
//       "field": "createParada",
//       "inputType": "createParadaInput",
//       "payloadType": "createParadaPayload",
//       "returnsField": "parada",
//       "inputFields": [
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String!",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "latitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "longitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "ventas",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "update": {
//       "kind": "update",
//       "field": "updateParada",
//       "inputType": "updateParadaInput",
//       "payloadType": "updateParadaPayload",
//       "returnsField": "parada",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "label",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "nombre",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "direccion",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "latitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "longitud",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "ventas",
//           "type": "[String]",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": true,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     },
//     "delete": {
//       "kind": "delete",
//       "field": "deleteParada",
//       "inputType": "deleteParadaInput",
//       "payloadType": "deleteParadaPayload",
//       "returnsField": "parada",
//       "inputFields": [
//         {
//           "name": "id",
//           "type": "ID!",
//           "namedType": "ID",
//           "kind": "SCALAR",
//           "required": true,
//           "isList": false,
//           "isRelation": false
//         },
//         {
//           "name": "clientMutationId",
//           "type": "String",
//           "namedType": "String",
//           "kind": "SCALAR",
//           "required": false,
//           "isList": false,
//           "isRelation": false
//         }
//       ]
//     }
//   }
// }
