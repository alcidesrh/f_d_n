// entity-engine/entityRegistry.ts
import { router as appRouter } from '@/router'
import { useSchemaStore } from '@/stores/autoimport/schemaStore'
import { StateStore } from '@/types/graphql'
import { watch } from 'vue'
import type { Router } from 'vue-router'
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

export async function getStore(
	entity?,
	refresh?,
	routerInstance?: Router,
): Promise<StateStore | false> {
	let entityName = entity as string | undefined

	if (!entityName) {
		const currentRoute = routerInstance?.currentRoute?.value ?? appRouter?.currentRoute?.value
		const routeEntity = currentRoute?.params?.entity
		entityName = Array.isArray(routeEntity) ? routeEntity[0] : (routeEntity as string | undefined)
	}

	if (!entityName) {
		throw new Error('No nombre de entidad')
	}

	const storeId = `${str.capitalize(entityName)}`
	const cachedStore = stores.get(storeId)

	if (cachedStore && !refresh) {
		return cachedStore as StateStore
	}

	const pinia = await getActivePinia()
	if (!pinia) {
		return false
	}

	await waitForSchema()

	const storeCreator = await storeFactory(storeId)
	if (!storeCreator) {
		return false
	}

	let store: StateStore & { init?: (refresh?: boolean) => Promise<void> }

	if (typeof storeCreator === 'function') {
		store = (storeCreator as any)(pinia) as StateStore & {
			init?: (refresh?: boolean) => Promise<void>
		}
	} else {
		store = storeCreator as StateStore & {
			init?: (refresh?: boolean) => Promise<void>
		}
	}

	if (!store) {
		return false
	}

	await store.init?.(refresh)
	stores.set(storeId, store)

	return store
}
