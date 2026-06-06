import { useSchemaStore } from '@/stores/autoimport/schemaStore'
import { boot } from 'quasar/wrappers'

export default boot(async ({ app, router, store }) => {
	const schemaStore = useSchemaStore()
	await schemaStore.loadEntities()
})
