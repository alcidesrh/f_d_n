<template>
	<div class="h-full w-full @container" :class="{ 'opacity-50': loading }">
		<!-- <pre v-if="store">{{ store.formSchema }}</pre> -->
		<div v-if="store?.formSchema && store.formSchema.length" class="form-container">
			<FormKit id="form" ref="form" type="form" @submit="submit" v-model="store.item" :actions="false">
				<div>
					<FormKitMessages />
				</div>
				<div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2">
					<FormKitSchema :schema="store.formSchema" :data="store.formData" :library="library">
						<template #crudBtn>
							<div class="flex flex-wrap justify-start gap-5 align-middle col-span-full mt-2">
								<slot name="CrudButton">
									<CrudButton :edit="!!store.item?.id" @submit="submit" @delete="store.remove()" @cancel="cancel" />
								</slot>
							</div>
						</template>
					</FormKitSchema>
				</div>
			</FormKit>
		</div>
		<div v-else class="w-full">
			<FormPreload :cols="2" />
		</div>
	</div>
</template>
<script setup lang="ts">
	import { TypeStore } from '@/types/graphql'
	import { reset } from '@formkit/core'
	import { FormKitMessages } from '@formkit/vue'
	import { useCloned } from '@vueuse/core'
	import { markRaw } from 'vue'

	const defaultValue = ref()
	let store: Ref<TypeStore> = ref()
	onBeforeMount(async () => {
		store.value = await getStore()
		defaultValue.value = useCloned(store.value.item).cloned.value
		watch(
			() => store.value.item,
			(v) => {
				defaultValue.value = useCloned(v).cloned.value
			},
		)
	})
	const library = markRaw({
		FormKitMessages: FormKitMessages,
	})

	const form = ref(null)
	const router = useRouter()

	const resetForm = ref(false)
	async function submit(data) {
		if (data == 'reset') {
			resetForm.value = true
			form.value.node.submit()
			return
		}
		try {
			await store.value.submit(data)
			bus.emit('positive', getAlertText())
			if (resetForm.value) {
				resetForm.value = false
				if (router.currentRoute.value.name == 'form') {
					if (!router.currentRoute.value.params?.id) {
						reset('form', {})
						store.value.item = {}
						return
					}
				}
				router.push({ path: `/form/${store.value.nameDecapitalize}` })
				return
			}
			store.value.collection(true)

			router.push({ name: 'list', params: { entity: store.value.name } })
		} catch (error) {
			cl.error(error)
		}
	}
	function cancel() {
		store.value.item = {}
		store.value.collection(true)
		router.push({
			name: 'list',
			params: {
				entity: store.nameDecapitalize,
			},
		})
	}
	// const tem = store.getFormData();
	// const data = ref({
	//   localidades: [], //items,
	//   parents: [],
	//   children: [],
	//   permisos: [],
	//   actions: [],
	//   roles: [],

	//   item: computed(() => store.item),
	//   // submit: (data) => store.submit(data),
	// });
	// onBeforeMount(() => {
	// 	const eventSource = new EventSource(
	// 		'http://localhost/.well-known/mercure?topic=form'
	// 	);

	// 	eventSource.onmessage = (event) => {
	// 		schema2.value = [JSON.parse(event.data).schema];
	// 	};

	// 	const eventSource2 = new EventSource(
	// 		'http://localhost/.well-known/mercure?topic=item'
	// 	);

	// 	eventSource.onmessage = (event) => {
	// 		schema2.value = [JSON.parse(event.data).schema];
	// 	}; });
</script>
