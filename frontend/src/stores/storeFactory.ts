import { useSchemaStore } from '@/stores/autoimport/schemaStore'
import { gql } from '@apollo/client/core'
import { useCloned } from '@vueuse/core'
import * as queryBuilder from 'gql-query-builder'
import { defineStore } from 'pinia'
import { Dialog } from 'quasar'
import { nextTick, watch } from 'vue'
import persist from './persist'

export default async (name: string) => {
	if (typeof entities.value[str.capitalize(name)] == 'undefined') {
		return false
	}
	const state = {
		name: name,
		entity: useCloned(entities.value[str.capitalize(name)]).cloned.value,
		config: {},
		items: [],
		item: {},
		options: [],
		excludeFields: ['legacyId'],
		labels: [{ createdAt: 'Fecha' }, { updatedAt: 'Actualizado' }],
		columns: [],
		visibleColumns: [],
		filters: {},
		orderField: 'id',
		orderType: 'DESC',
		formSchema: [],
		formData: {},
		formGroups: [],
		pagination: undefined as
			| {
					itemsPerPage: number
					lastPage: number | null
					totalCount: number | null
					currentPage: number
					hasNextPage: boolean | null
			  }
			| undefined,
	}
	if (entities.value[name]?.pagination) {
		state.pagination = {
			itemsPerPage: 25,
			lastPage: null,
			totalCount: null,
			currentPage: 1,
			hasNextPage: null,
		}
	}
	return await defineStore(name, {
		persist: {
			...persist,
		},
		state: () => state,
		getters: {
			computedColumns: (store) =>
				store.columns.filter((v) => [
					{
						name: 'index',
						label: '#',
						field: 'index',
					},
					...store.visibleColumns.find((v2) => v2 == v.field),
				]),
			computedFormFields: (s) => s.config.formFields.filter((v) => v.visible),
			nameDecapitalize: (store) => str.decapitalize(store.name),
			collectionEndpoint(store) {
				if (store.name == 'Status') {
					return 'statuses'
				}
				return `${str.decapitalize(store.name)}s`
			},
			mutationOperation: (store) => {
				return store.item?.id ? `update${store.name}` : `create${store.name}`
			},

			iri: (store) => {
				if (!store.item?.id) return null
				return `/api/${str.decapitalize(store.name)}s/${store.item.id}`
			},

			collectionVariables(store): Record<string, {}> {
				if (!store.entity) return {}
				const variables = {}
				if (store.entity?.queries.collection?.args && typeof store.entity?.queries.collection.args['currentPage'] != 'undefined') {
					variables.currentPage = {
						...store.entity?.queries.collection.args['currentPage'],
						value: store.pagination?.currentPage,
					}
					variables.itemsPerPage = {
						...store.entity?.queries.collection.args['itemsPerPage'],
						value: store.pagination?.itemsPerPage,
					}
				}

				const filters = useCloned(store.filters).cloned.value
				const args = store.entity?.queries.collection.args //useCloned(store.entity?.queries.collection.args).cloned.value
				// ⚠️ getters no tienen acceso directo a otros getters vía store
				// necesitas usar `this`
				const columns = this.computedColumns

				columns
					.filter((v) => v.filterable)
					.forEach((v) => {
						if (v.relatedTo) {
							variables[`${v.field}_id_list`] = args[`${v.field}_id_list`]

							if (v.field in filters && filters[v.field]) {
								variables[`${v.field}_id_list`].value = filters[v.field].map((i) => getIdFromIri(i.id))
							}
						} else if (typeof args[v.field] != 'undefined') {
							variables[v.field] = args[v.field]
							if (v.field in filters) {
								variables[v.field].value = filters[v.field]
							}
						}
					})

				if (args?.order) {
					const order = {}
					order[store.orderField] = store.orderType
					variables.order = { ...args?.order, value: [order] }
				}
				return util.isEmpty(variables) ? null : variables
			},

			collectionFields(store): Array<{}> {
				if (!store.entity) return []

				const collectionQuery = store.entity?.queries.collection
				const entityFields = store.entity?.fields
				const fields: any[] = []

				const columns = (this as any).computedColumns

				collectionQuery?.fields.forEach((v, i) => {
					fields[i] = {}

					if (v.name === 'collection') {
						fields[i][v.name] = columns.map((col) => {
							const f = entityFields[col.field]
							if (f.relatedTo && f.type !== 'ENUM') {
								return {
									[str.decapitalize(col.field)]: ['id', 'label'],
								}
							}

							return col.field
						})
					} else {
						fields[i][v.name] = types.value[v.type]
					}
				})

				return fields
			},
		},
		actions: {
			async init(refresh = false) {
				if (!Object.keys(this.config).length || refresh) {
					const schemaStore = useSchemaStore()
					if (!schemaStore.isLoaded) {
						await new Promise<void>((resolve) => {
							const unwatch = watch(
								() => schemaStore.isLoaded,
								(val) => {
									if (val) {
										unwatch()
										resolve()
									}
								},
							)
						})
					}
					const restApi = await useApi()
					const response = await restApi.value.get('/entity_configurations?entityClass=' + this.name)
					this.config = response['member'][0]
					if (refresh) {
						this.setColumns(true)
						this.getFormSchema(true)
					}
				}
			},
			resetColumns() {
				const t: any[] = []

				for (const v of this.config.collectionFieldConfig.filter((v) => v.visible)) {
					const item = this.columns.find((v2) => v2.field === v.field)
					if (!item) continue
					item.position = v.position
					item.visible = true
					t.push(item)
				}
				this.columns = t
				this.visibleColumns = this.columns.map((v) => v.field)
			},
			async setColumns(refresh = false) {
				if (this.columns.length && refresh) {
					this.resetColumns()
				} else if (!this.columns.length || refresh) {
					this.columns = []
					for (let v of this.config.collectionFieldConfig.filter((v) => v.visible)) {
						v = useCloned(v).cloned.value
						const field = this.entity.fields[v.field]
						const args = this.entity.queries.collection.args
						if (v.filterable && typeof args[v.field] != 'undefined') {
							const input = { ...field.input }
							delete input.label
							v.schema = {
								...input,
								name: v.field,
								id: v.id,
								loading: '$loading',
								outerClass: `mb-0! col-wraper formkit-${v.field}`,
								dense: true,
							}
							if (v.schema['$formkit'] === 'text') {
								v.schema['$formkit'] = 'text_search'
							}
							if (field.type === 'Date') {
								v.schema.range = true
							}
							if (v.schema.$formkit === 'select') {
								const storeTemp = await getStore(field.relatedTo)
								v.schema.options = await storeTemp.getOptions()
								v.relatedTo = field.relatedTo
							}
						}
						this.columns.push(v)
						this.visibleColumns.push(v.field)
					}
				}
			},
			async collection(force = false) {
				await this.setColumns()
				const qb = queryBuilder.query(
					{
						operation: this.collectionEndpoint,
						variables: this.collectionVariables,
						fields: this.collectionFields,
					},
					null,
					{
						operationName: 'getUsers',
					},
				)
				const apollo = useApolloStore()
				const { data } = await apollo.client.query({
					query: gql(qb.query),
					variables: qb.variables,
					fetchPolicy: !force ? 'cache-first' : 'cache-and-network',
				})
				if (this.pagination) {
					this.items = data[this.collectionEndpoint].collection
					Object.assign(this.pagination, data[this.collectionEndpoint].paginationInfo)
				} else {
					this.items = data[this.collectionEndpoint]
				}

				nextTick(() => highlighted(this.computedColumns, this.filters))

				return data
			},

			async getItem(id?: string | number) {
				const variables = {}
				for (const [key, value] of Object.entries(this.entity.queries.item.args)) {
					variables[key] = { ...value, value: id }
				}
				const fields = ['id']
				this.computedFormFields.forEach((v) => {
					if (this.entity.fields[v.field]?.relatedTo) {
						const temp = {}
						temp[v.field] = ['label', 'id']
						fields.push(temp)
					} else {
						fields.push(v.field)
					}
				})
				const query = queryBuilder.query({
					operation: this.nameDecapitalize,
					variables: variables,
					fields: fields,
				})
				const { data } = await useApolloStore().query({
					query: gql(query.query),
					variables: query.variables, //{ id: this.getIriFromId(id) },
					fetchPolicy: 'cache-first',
				})

				this.item = useCloned(data[this.nameDecapitalize]).cloned.value
			},
			async selectOptions(v) {
				if (v?.children && Array.isArray(v.children)) {
					for (let index = 0; index < v.children.length; index++) {
						await this.selectOptions(v.children[index])
					}
				} else if (Array.isArray(v)) {
					for (let index = 0; index < v.length; index++) {
						await this.selectOptions(v[index])
					}
				} else if (v.name && this.entity.fields[v.name]?.relatedTo) {
					const temp = await getStore(this.entity.fields[v.name]?.relatedTo)
					await temp.getOptions()
					this.formData[temp.nameDecapitalize + 's'] = temp.options
				}
			},
			async getFormSchema(refresh = false) {
				if (refresh) {
					this.formSchema = []
				} else {
					this.item = {}
				}

				if (this.formSchema.length) {
					await this.selectOptions(this.formSchema)
					// for (let index = 0, relatedTo = null; index < this.formSchema.length; index++) {
					// 	const v = this.formSchema[index]
					// 	if (v.name && (relatedTo = this.entity.fields[v.name]?.relatedTo)) {
					// 		const temp = await getStore(relatedTo)
					// 		await temp.getOptions()
					// 		this.formData[temp.nameDecapitalize + 's'] = temp.options
					// 	}
					// }
					return
				}

				let fields: any[] = []
				for (const v of this.computedFormFields) {
					const field = this.entity.fields[v.field]
					if (!field) {
						continue
					}

					if (field.relatedTo) {
						const temp = await getStore(field.relatedTo)
						await temp.getOptions()
						this.formData[temp.nameDecapitalize + 's'] = temp.options
					}
					fields.push({
						...v,
						input: { ...field.input, ...v.input },
					})
				}

				if (fields.length == 0) {
					fields = Object.values(this.entity.fields)
				}

				this.formSchema = [
					{
						$el: 'div',
						children: '$slots.crudBtn',
					},
					{
						$el: 'div',
						attrs: { class: 'grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2' },
						children: fields.map((v) => {
							const input = v.input
							input.attrs = { class: 'alcides' }
							if (input?.$el === 'fieldset') {
								return {
									...input,
									attrs: { ...input.attrs, class: 'md:col-span-2' },
								}
							}
							return input
						}),
					},
				]
				return this.formSchema
			},
			remove(arg?: any) {
				const item = arg || this.item

				Dialog.create({
					title: 'Eliminar',
					message: getAlertText('remove', item?.nombre || item?.label || item?.id || 'este elemento.'),
					cancel: true,
					persistent: true,
					html: true,
				}).onOk(async () => {
					this.entity.mutations.delete.args.input.value = { id: item.id }

					const operation = `delete${this.name}`

					const query = queryBuilder.mutation({
						operation,
						variables: this.entity.mutations.delete.args,
						fields: [{ [str.decapitalize(this.name)]: ['id'] }],
					})

					const { error } = await useApolloStore().mutate({
						mutation: gql(query.query),
						variables: query.variables,
						context: { keepId: true },
					})

					if (error) return

					bus.emit('positive', getAlertText('remove_after'))

					await this.collection(true)

					const router = useRouter()
					if (router.currentRoute.value.name !== 'list') {
						router.push({ name: 'list', params: { entity: this.name } })
					}
				})
			},
			removeMultiple(items: any[]) {
				Dialog.create({
					title: 'Eliminar',
					message: getAlertText('remove', `${items.length} elementos`),
					cancel: true,
					persistent: true,
					html: true,
				}).onOk(async () => {
					const query = queryBuilder.mutation({
						operation: 'deleteAgnostic',
						variables: {
							input: {
								type: 'deleteAgnosticInput!',
								value: {
									ids: items.map((i) => getIdFromIri(i.id)),
									resource: this.name,
								},
							},
						},
						fields: [{ agnostic: ['id'] }],
					})

					const { error } = await useApolloStore().mutate({
						mutation: gql(query.query),
						variables: query.variables,
					})

					if (error) return

					bus.emit('positive', getAlertText('remove_after'))
					await this.collection(true)
				})
			},
			async getOptions(entities?: string[]) {
				if (entities) {
					const queries = entities.map((e) => ({
						operation: { name: 'collectionAgnostic', alias: e },
						fields: ['data'],
						variables: {
							[e]: { name: 'resource', type: 'String', value: e },
						},
					}))

					const q = queryBuilder.query(queries)

					await useApolloStore().query({
						query: gql(q.query),
						variables: q.variables,
						context: { noLoading: true },
					})

					return
				}

				if (!this.options.length) {
					const query = queryBuilder.query({
						operation: 'collectionAgnostic',
						fields: ['data'],
						variables: {
							resource: { type: 'String', value: this.name },
						},
					})

					const { data } = await useApolloStore().query({
						query: gql(query.query),
						variables: query.variables,
						context: { noLoading: true },
					})

					this.options = data.collectionAgnostic.data
				}

				return this.options
			},
			orderColumns(i: number, to: 'left' | 'right') {
				const temp = this.columns[i]

				if (to === 'left' && i > 0) {
					this.columns[i] = this.columns[i - 1]
					this.columns[i - 1] = temp
				} else if (to === 'right' && i < this.columns.length - 1) {
					this.columns[i] = this.columns[i + 1]
					this.columns[i + 1] = temp
				}

				this.columns.forEach((v, idx) => (v.position = idx + 1))
			},
			async submit() {
				const input = this.item.id ? this.entity?.mutations.update.args.input : this.entity?.mutations.create.args.input
				input.value = this.item
				const query = queryBuilder.mutation({
					operation: this.mutationOperation,
					variables: { input },
					fields: ['clientMutationId'],
				})

				const { data, error } = await useApolloStore().mutate({
					mutation: gql(query.query),
					variables: query.variables,
				})
				if (error) {
					throw new Error(error.message)
					return false
				}
				return data
				// await this.collection('network-only')

				// const router = useRouter()
				// if (router.currentRoute.value.name !== 'list') {
				// 	router.push({ name: 'list', params: { entity: this.name } })
				// }
			},
		},
	})()

	// return useStore()
}
