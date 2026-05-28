// src/graphql/links/loadingLink.ts
import { ApolloLink, Observable } from '@apollo/client/core'
import { LoadingBar } from 'quasar'
import { watch } from 'vue'

type Ctx = {
	noLoading?: boolean
	loadingKey?: string
	priority?: number
	delayMs?: number // anti-flicker
}
let loadingStore = ref({})
export function createLoadingLink(pinia) {
	return new ApolloLink((operation, forward) => {
		loadingStore.value = useLoadingStore()
		const ctx = operation.getContext() as Ctx
		if (ctx.noLoading) return forward(operation)
		const key = ctx.loadingKey || operation.operationName || (operation.query?.definitions?.[0] as any)?.name?.value || 'anonymous'

		const priority = ctx.priority ?? 1
		const delayMs = ctx.delayMs ?? 150

		let started = false
		let timer: any = null

		// anti-flicker: solo iniciar si supera delay
		timer = setTimeout(() => {
			loading.value = true
			loadingStore.value.start(key, priority)
			started = true
		}, delayMs)

		return new Observable((observer) => {
			const sub = forward(operation).subscribe({
				next: (v) => observer.next(v),
				error: (e) => {
					if (timer) clearTimeout(timer)
					loading.value = false
					if (started) loadingStore.value.stop(key)
					observer.error(e)
				},
				complete: () => {
					if (timer) clearTimeout(timer)
					loading.value = false
					if (started) loadingStore.value.stop(key)
					observer.complete()
				},
			})

			return () => {
				if (timer) clearTimeout(timer)
				loading.value = false
				if (started) loadingStore.value.stop(key)
				sub.unsubscribe()
			}
		})
	})
}
LoadingBar.setDefaults({
	color: 'info',
	size: '5px',
	// position: "bottom",
})
watch(
	() => ({
		loading: loadingStore.value?.loading,
		p: loadingStore.value?.highestPriority,
	}),
	({ loading, p }) => {
		// ejemplo de política:
		// p>=3: barra + overlay (lo decides en layout)
		// p=2: barra
		// p=1: nada (o spinner local)
		if (loading && p >= 1) LoadingBar.start()
		else LoadingBar.stop()
	},
	{ deep: true },
)
