import { router } from '@/router'
export function useRouter() {
	return router
}

export function useRoute() {
	return router.currentRoute.value
}
