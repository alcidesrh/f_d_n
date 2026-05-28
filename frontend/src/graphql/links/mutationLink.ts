import { ApolloLink } from '@apollo/client/core'
export function createMutationLink() {
	return new ApolloLink((operation, forward) => {
		if (operation.operationType == 'mutation') {
			const ctx = operation.getContext()
			if (!ctx?.keepId && operation.variables.input) {
				const input = operation.variables.input
				Object.keys(input).forEach((k) => {
					if (util.isObject(input[k]) && !!input[k]?.id) {
						input[k] = input[k].id
					} else if (util.isArray(input[k])) {
						const temp = input[k]
						input[k] = []
						temp.forEach((v, i) => {
							if (util.isObject(v) && !!v?.id) {
								input[k].push(v.id)
							} else {
								input[k].push(v)
							}
						})
					}
				})
			}
		}
		return forward(operation)
	})
}
