import { defineBoot } from '#q-app/wrappers'
import { useUserSessionStore } from '@/stores/autoimport/session'

export default defineBoot(async ({ router }) => {
	router.beforeEach(async (to, from) => {
		const session = useUserSessionStore()

		// Rutas públicas que no requieren autenticación
		const publicRoutes = ['login']
		if (publicRoutes.includes(to.name as string)) {
			if (session.isAuthenticated && to.name === 'Login') {
				return { path: '/' }
			}
			return true
		}

		// Verificar autenticación
		if (!session.isAuthenticated) {
			session.redirectTo = to.fullPath
			// return false
			return { name: 'login' }
		}
		// Verificar permiso específico si la ruta lo requiere
		const requiredPermission = to.meta?.requiresPermission as string | undefined
		if (requiredPermission && !session.can(requiredPermission)) {
			return { name: 'forbidden' }
		}

		const entity = to.params.entity as string
		if (!entity) {
			return true
		}
		const store = await getStore(entity)
		if (to.meta?.action == 'listar') {
			if (!store) {
				return 'lista/' + entity + '/404'
			}
			store.collection()
		} else if (to.meta.action == 'form') {
			await store.getFormSchema()
			const id = to.params.id as string | undefined
			if (id) {
				store.getItem(id)
			} else {
				store.item = {}
			}
		}
		return true
	})
})
