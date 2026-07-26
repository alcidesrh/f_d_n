import type { RouteRecordRaw } from 'vue-router'
import { config } from '@/config/config'
import routes from '@/router/routes'

interface SyncableRoute {
	name: string
	path: string
	children: SyncableRoute[]
}

function extractSyncableRoutes(rawRoutes: RouteRecordRaw[]): SyncableRoute[] {
	return rawRoutes
		.filter((r) => !r.meta || !r.meta.nosync)
		.map((route) => ({
			name: String(route.name || ''),
			path: route.path,
			children: route.children ? extractSyncableRoutes(route.children) : [],
		}))
}

export async function syncVueRoutes(): Promise<unknown> {
	const payload = { routes: extractSyncableRoutes(routes) }
	const response = await fetch(`${config.ENTRYPOINT}/vue-routes/sync`, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(payload),
	})

	if (!response.ok) {
		throw new Error(`Sync failed: ${response.status} ${response.statusText}`)
	}

	return response.json()
}
