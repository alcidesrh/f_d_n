// src/boot/apollo.ts
import { defineBoot } from '#q-app/wrappers'
import { createAuthLink } from '@/graphql/links/authLink'
import { createErrorLink } from '@/graphql/links/errorLink'
import { createLoadingLink } from '@/graphql/links/loadingLink'
import { createMutationLink } from '@/graphql/links/mutationLink'
import { createQueryLink } from '@/graphql/links/queryLink'
import removeTypenameLink from '@/graphql/links/removeTypenameLink'
import { ApolloClient, ApolloLink, HttpLink, InMemoryCache } from '@apollo/client'
import { DefaultApolloClient } from '@vue/apollo-composable'

// const tabIdCoordinator = new TabIdCoordinator()
// const tabId = tabIdCoordinator.tabId

// export { tabId }

function createProfilerFetch() {
	return async (uri: RequestInfo | URL, options: RequestInit) => {
		const response = await fetch(uri, options)
		const token = response.headers.get('X-Debug-Token')
		if (token) {
			useProfilerStore().setToken(token)
		}
		return response
	}
}

export default defineBoot(async ({ app, pinia }) => {
	const httpLink = new HttpLink({
		uri: config.ENTRYPOINT_GRAPHQL,
		fetch: createProfilerFetch(),
	})

	const debugLink = new ApolloLink((operation, forward) => {
		return forward(operation)
	})
	const apolloClient = new ApolloClient({
		assumeImmutableResults: true,
		connectToDevTools: true,
		link: ApolloLink.from([
			removeTypenameLink,
			createQueryLink(),
			// debugLink(),
			createMutationLink(),
			createAuthLink(),
			createErrorLink(),
			createLoadingLink(pinia),
			// removeTypenameLink,
			httpLink,
		]),
		cache: new InMemoryCache({
			typePolicies: {
				EntityConfiguration: {
					keyFields: ['entityClass'],
				},
			},
		}),
		queryDeduplication: false,
		defaultOptions: {
			watchQuery: { fetchPolicy: 'no-cache' },
			query: { fetchPolicy: 'cache-first' },
			mutate: { errorPolicy: 'all' },
		},
		// defaultOptions: {
		//   watchQuery: {
		//     notifyOnNetworkStatusChange: false,
		//     fetchPolicy: "cache-and-network",
		//   },
		// },
	})
	const store = useApolloStore()
	store.setClient(apolloClient)
	app.provide(DefaultApolloClient, apolloClient)

	if (typeof window !== 'undefined') {
		window.__APOLLO_CLIENT__ = apolloClient
	}
})
