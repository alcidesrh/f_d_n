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
			const temp2 = temp.find(
				(i) => i.arguments.find((i) => i.name.value == 'id'),
				// && i.selectionSet.selections.filter((i) => i.name.value == "collection").length == 0,
			)

			if (temp2) {
				if (operation.variables?.value) {
					operation.variables.value = `/api/${temp2.name.value}s/${operation.variables.value}`
				} else if (operation.variables?.id) {
					operation.variables.id = `/api/${temp2.name.value}s/${operation.variables.id}`
				}
			}
		}
		return forward(operation)
	})
}
