// src/boot/api.ts
import { createApi, setApi } from '@/composables/useApiRest'
import { config } from '@/config/config'
import { LoadingBar } from 'quasar'
import { boot } from 'quasar/wrappers'

export default boot(({ app, router }) => {
	const session = useUserSessionStore()
	const loadingStore = useLoadingStore()

	const api: ReturnType<typeof createApi> = createApi({
		baseURL: config.ENTRYPOINT,

		getAccessToken: () => session.token, //localStorage.getItem('token'),

		// refreshToken: async () => {
		// 	const r = await restApi.get('/auth/refresh')
		// 	if (!r.ok) return null
		// 	const d = await r.json()
		// 	localStorage.setItem('token', d.token)
		// 	return d.token
		// },

		onStart: (key?) => {
			LoadingBar.start()
			loadingStore.start(key || 'anonymous')
		},
		onEnd: (key?) => {
			LoadingBar.stop()
			loadingStore.stop(key || 'anonymous')
		},
	})
	// cancelar requests al cambiar de ruta
	router.beforeEach(() => {
		api.cancelAll()
	})
	setApi(api)
})
