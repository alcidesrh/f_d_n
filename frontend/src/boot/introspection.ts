import { useSchemaStore } from '@/stores/autoimport/schemaStore'
import { boot } from 'quasar/wrappers'

export default boot(async ({ app, router, store }) => {
	const session = useUserSessionStore()
	if (session.isAuthenticated) {
		const schemaStore = useSchemaStore()
		await schemaStore.loadEntities()
	}
})
