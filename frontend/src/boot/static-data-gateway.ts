import { defineBoot } from '#q-app/wrappers'
import { useStaticDataGateway } from '@/services/StaticDataGateway'
import { getVersions, setVersion } from '@/utils/configVersions'

export default defineBoot(async ({ router }) => {
	const gateway = useStaticDataGateway()

	const restApi = useApi()

	try {
		const response: any = await restApi.value.get('/config-versions')
		const member = response?.['member']?.[0]
		const serverVersions = member?.versions || {}
		const localVersions = getVersions()
		for (const [entityClass, serverTimestamp] of Object.entries(serverVersions)) {
			const localTimestamp = localVersions[entityClass]
			if (localTimestamp && localTimestamp !== serverTimestamp) {
				// const storeId = `${entityClass.charAt(0).toLowerCase() + entityClass.slice(1)}Store`
				// localStorage.removeItem(`pinia_${storeId}`)
				try {
					const store = await getStore(entityClass)
					if (store) {
						await store.init(true)
					}
				} catch {}
			}
		}

		if (serverVersions['schema'] && localVersions['schema'] !== serverVersions['schema']) {
			const schemaStore = useSchemaStore()
			schemaStore.entities = {}
			schemaStore.types = {}
			await schemaStore.loadEntities()
			// localStorage.removeItem('pinia_schemaStore')
		}

		for (const [entityClass, timestamp] of Object.entries(serverVersions)) {
			setVersion(entityClass, timestamp)
		}
	} catch {}

	gateway.register('entity_configuration', async (payload) => {
		const entityClass = payload.entityClass as string
		if (!entityClass) return
		setVersion(entityClass, payload.updatedAt)
		try {
			const store = await getStore(entityClass)
			if (store) {
				await store.init(true)
			}
		} catch {}
	})

	gateway.register('graphql_schema', async () => {
		setVersion('schema', new Date().toISOString())

		const schemaStore = useSchemaStore()
		schemaStore.entities = {}
		schemaStore.types = {}
		await schemaStore.loadEntities()
	})

	gateway.start()

	router.afterEach(() => {})
})
