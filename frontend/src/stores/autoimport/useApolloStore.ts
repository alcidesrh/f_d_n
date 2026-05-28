import { provideApolloClient, useLazyQuery } from '@vue/apollo-composable'
import { defineStore } from 'pinia'

export const useApolloStore = defineStore('apollo', () => {
	const client = ref() //inject('DefaultApolloClient')

	const loading = ref(false)
	const error = ref(null)
	const data = ref(null)

	function setClient(pclient) {
		client.value = pclient
	}
	function createLazy(query, options = {}) {
		return provideApolloClient(client.value)(() => {
			const { load, result, loading: l, error: e } = useLazyQuery(query, null, { fetchPolicy: 'cache-first', ...options })

			loading.value = l
			error.value = e
			data.value = result

			return {
				execute: (variables) => load(null, variables),
				result,
				loading: l,
				error: e,
			}
		})
	}

	function runQuery(query, variables = {}, options = {}) {
		return provideApolloClient(client)(() => {
			const q = useQuery(query, variables, {
				fetchPolicy: 'cache-first',
				...options,
			})

			loading.value = q.loading
			error.value = q.error
			data.value = q.result

			return q
		})
	}

	async function query(options) {
		return await client.value.query(options)
	}

	async function mutate(options) {
		return await client.value.mutate(options)
	}

	return {
		loading,
		error,
		data,
		createLazy,
		runQuery,
		client,
		setClient,
		query,
		mutate,
	}
})
