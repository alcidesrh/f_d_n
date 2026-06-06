import { ApolloLink } from '@apollo/client/core'
export function createQueryLink() {
	return new ApolloLink((operation, forward) => {
		let temp
		if (
			operation.operationType == 'query' &&
			// operation?.variables.id
			(temp = operation.query.definitions[0].selectionSet.selections)
			// Object.keys(operation.variables).includes("id")
		) {
			// const temp = operation.query.definitions[0].selectionSet.selections
			const temp2 = temp.find((i) => i.arguments.find((i) => i.name.value == 'id'))
			if (temp2 && !temp2.selectionSet.selections.some((v) => v.name.value == 'collection')) {
				if (operation.variables?.value) {
					operation.variables.value = `/api/${temp2.name.value}/${operation.variables.value}`
				} else if (operation.variables?.id) {
					operation.variables.id = `/api/${temp2.name.value}s/${operation.variables.id}`
				}
			}
		}
		return forward(operation)
	})
}
