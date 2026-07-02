type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

type RequestConfig = {
	url: string
	method?: HttpMethod
	params?: Record<string, any>
	data?: any
	headers?: Record<string, string>
	signal?: AbortSignal
	timeout?: number
	responseType?: 'json' | 'text' | 'blob'
	retry?: number
	retryDelay?: number
	cacheTTL?: number
	cacheTags?: string[]
	dedupe?: boolean
	trace?: boolean
	key?: string
}

type ApiOptions = {
	baseURL: string
	getAccessToken?: () => string | null
	refreshToken?: () => Promise<string | null>
	onStart?: () => void
	onEnd?: () => void
}

export function createApi(options: ApiOptions) {
	const { baseURL, getAccessToken, refreshToken, onStart, onEnd } = options

	const cache = new Map<string, any>()
	const inflight = new Map<string, Promise<any>>()
	const controllers = new Set<AbortController>()

	let refreshing: Promise<string | null> | null = null

	const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms))

	const keyOf = (c: RequestConfig) => `${c.method}-${c.url}-${JSON.stringify(c.params)}-${JSON.stringify(c.data)}`

	const buildURL = (url: string, params?: any) => {
		const qs = new URLSearchParams(params || {}).toString()
		return baseURL + url + (qs ? `?${qs}` : '')
	}

	function invalidate(tags: string[]) {
		for (const [k, v] of cache.entries()) {
			if (v.tags?.some((t: string) => tags.includes(t))) {
				cache.delete(k)
			}
		}
	}

	async function request<T>(cfg: RequestConfig): Promise<T> {
		const key = keyOf(cfg)

		// cache
		if (cfg.cacheTTL && cache.has(key)) {
			const c = cache.get(key)
			if (c.exp > Date.now()) return c.data
		}

		// dedupe
		if (cfg.dedupe && inflight.has(key)) return inflight.get(key)!

		const exec = async (): Promise<T> => {
			onStart?.(cfg.key)

			const controller = new AbortController()
			controllers.add(controller)

			try {
				const headers: any = { ...cfg.headers }
				const token = getAccessToken?.()
				if (token) headers['Authorization'] = `Bearer ${token}`

				let body: any
				if (cfg.data instanceof FormData) body = cfg.data
				else if (cfg.data) {
					headers['Content-Type'] = 'application/json'
					body = JSON.stringify(cfg.data)
				}

				const res = await fetch(buildURL(cfg.url, cfg.params), {
					method: cfg.method || 'GET',
					headers,
					body,
					signal: cfg.signal || controller.signal,
				})

				const profilerToken = res.headers.get('X-Debug-Token')
				if (profilerToken) {
					useProfilerStore().setToken(profilerToken)
				}

				if (!res.ok) {
					const text = await res.text()
					let err
					try {
						err = JSON.parse(text)
					} catch {
						err = text
					}
					if ((cfg.retry ?? 0) > 0) {
						await sleep(cfg.retryDelay ?? 500)
						return request({ ...cfg, retry: (cfg.retry ?? 0) - 1 })
					}
					if (res.status === 401) {
						const session = useUserSessionStore()
						session.clear()

						throw 'Usuario o contraseña incorrecto.'
						// if (!refreshToken) {
						// 	const newToken = await refreshing
						// 	refreshing = null
						// 	if (newToken) return exec()
						// }
					}
					throw err?.error || err
				}

				let data: any = await res.json()

				if (cfg.cacheTTL) {
					cache.set(key, {
						data,
						exp: Date.now() + cfg.cacheTTL,
						tags: cfg.cacheTags || [],
					})
				}

				return data
			} finally {
				controllers.delete(controller)
				onEnd?.(cfg.key)
			}
		}

		const p = exec()
		if (cfg.dedupe) inflight.set(key, p)

		try {
			return await p
		} finally {
			inflight.delete(key)
		}
	}

	return {
		request,
		get: (url: string, c?: any) => request({ ...c, url, method: 'GET' }),
		post: (url: string, data?: any, c?: any) => request({ ...c, url, method: 'POST', data }),
		put: (url: string, data?: any, c?: any) => request({ ...c, url, method: 'PUT', data }),
		patch: (url: string, data?: any, c?: any) => request({ ...c, url, method: 'PATCH', data }),
		delete: (url: string, c?: any) => request({ ...c, url, method: 'DELETE' }),

		invalidate,
		cancelAll: () => controllers.forEach((c) => c.abort()),
	}
}

// export let restApi: Ref<ReturnType<typeof createApi>> = ref()
export let restApi: ReturnType<typeof createApi>

export function useApi() {
	if (!restApi) {
		throw new Error('API no inicializada. Probablemente estás usando useApi() antes del boot.')
	}
	return restApi
}
export function setApi(api) {
	// restApi = api
	restApi = api
}
