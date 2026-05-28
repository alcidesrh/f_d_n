import { ApolloLink } from '@apollo/client/core'

export function createAuthLink() {
	return new ApolloLink((operation, forward) => {
		// const store = useUserSessionStore();
		operation.setContext({
			headers: {
				Authorization: `fdn_459d648b686311423af49288c6a5f7a372c6085875efcb5efa3e00474ad912a6`,
				// Authorization: `Bearer ${store.user.token}`,
				// Tabid: tabId,
			},
		})
		return forward(operation)
	})
}
