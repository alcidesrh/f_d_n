// entity-engine/entityRegistry.ts
import { useSchemaStore } from '@/stores/autoimport/schemaStore'
import { StateStore } from '@/types/graphql'
import { watch } from 'vue'
import storeFactory from '../stores/storeFactory'

// export const entities = ref({});
export const stores = new Map()

function waitForSchema(): Promise<void> {
	const schemaStore = useSchemaStore()
	if (schemaStore.isLoaded) return Promise.resolve()
	return new Promise((resolve) => {
		const unwatch = watch(
			() => schemaStore.isLoaded,
			(val) => {
				if (val) {
					unwatch()
					resolve()
				}
			},
		)
	})
}

export function registerEntity(entity) {
	entities.value[entity.name] = entity
}

export function getEntity(name) {
	return entities.value[name]
}

export async function getStore(entity?): StateStore {
	if (!entity && !(entity = useRoute().params.entity)) {
		throw Error(`No nombre de entidad`)
	}
	let store
	const storeId = `${str.capitalize(entity)}`

	if (!(store = stores.get(storeId))) {
		const pinia = await getActivePinia()

		if (!pinia || !(storeId in pinia.state.value)) {
			await waitForSchema()

			if (!(store = await storeFactory(storeId))) {
				// throw Error(`entityRegistry linea 28: No se pudo crear la store de nombre ${entity}`)
				return false
			} else {
				await store.init()
				stores.set(storeId, store)
			}
		} else {
			return await defineStore(storeId)()
		}
	} else {
		// alert('storeId')
	}
	return store
}
