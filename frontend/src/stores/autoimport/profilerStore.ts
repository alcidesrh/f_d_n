import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useProfilerStore = defineStore('profiler', () => {
	const debugToken = ref<string | null>(null)

	function setToken(token: string | null) {
		debugToken.value = token
	}

	return { debugToken, setToken }
})
