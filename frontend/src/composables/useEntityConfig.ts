import { gql } from '@apollo/client/core'
import { useCloned } from '@vueuse/core'

export interface CollectionFieldConfig {
	id: string
	field: string
	position: number
	visible: boolean
	sortable?: boolean
	filterable?: boolean
	label: string
	attrs: null
	name: string
	groupName?: null
}

export interface FormField {
	id: string
	field: string
	position: number
	visible: boolean
	groupName: null
	attrs: null
	label: string
	name: string
	__typename: string
}

export interface EntityConfig {
	id: number
	entityClass: string
	collectionFieldConfig: CollectionFieldConfig[]
	formFields: FormField[]
}

const GET_ALL_CONFIGS = gql`
	query GetAllConfigs {
		entityConfigurations {
			id
			entityClass
		}
	}
`

const GET_CONFIG = gql`
	query GetConfig($entityClass: String!) {
		entityConfigurations(entityClass: $entityClass) {
			entityClass
			collectionFieldConfig {
				id
				field
				position
				visible
				sortable
				filterable
				label
				attrs
				name
			}
			formFields {
				id
				field
				position
				visible
				groupName
				attrs
				label
				name
			}
		}
	}
`

const GET_CONFIG_ADMIN = gql`
	query GetConfig($entityClass: String!) {
		entityConfigurations(entityClass: $entityClass) {
			entityClass
			collectionFieldConfig {
				id
				field
				position
				visible
				sortable
				filterable
				label
				attrs
				name
			}
			formFields {
				id
				field
				position
				visible
				groupName
				attrs
				label
				name
			}
		}
	}
`

const UPDATE_CONFIG = gql`
	mutation UpdateConfig($input: updateWithRelationsEntityConfigurationInput!) {
		updateWithRelationsEntityConfiguration(input: $input) {
			entityConfiguration {
				id
				entityClass
			}
		}
	}
`

export const useEntityConfig = () => {
	const apollo = useApolloStore()

	const fetchAllConfigs = async (): Promise<any[]> => {
		const { data } = await apollo.query({ query: GET_ALL_CONFIGS })
		return data.entityConfigurations
	}

	const fetchConfig = async (entityClass: string): Promise<EntityConfig> => {
		const { data } = await useApolloStore().query({
			query: GET_CONFIG,
			variables: { entityClass },
			context: {
				noLoading: true,
			},
		})
		return data.entityConfigurations[0]
	}

	const fetchConfigAdmin = async (entityClass: string): Promise<EntityConfig> => {
		const { data } = await useApolloStore().query({
			query: GET_CONFIG_ADMIN,
			variables: { entityClass },
		})
		return useCloned(data.entityConfigurations[0]).cloned.value
	}

	const saveConfig = async (input) => {
		return await useApolloStore().mutate({
			mutation: UPDATE_CONFIG,
			variables: { input },
			context: {
				keepId: true,
			},
		})
	}

	return { fetchAllConfigs, fetchConfig, saveConfig, fetchConfigAdmin }
}
