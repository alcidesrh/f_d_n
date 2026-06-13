import { ApolloLink } from '@apollo/client/core'

export function createAuthLink() {
	return new ApolloLink((operation, forward) => {
		const headers: Record<string, string> = {}
		const sessionStore = useUserSessionStore()
		if (sessionStore.token) {
			headers['Authorization'] = `Bearer ${sessionStore.token}`
		}
		operation.setContext({ headers })
		return forward(operation)
	})
}
