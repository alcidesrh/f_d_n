<template>
	<q-input
		outlined
		dense
		flat
		bg-color="white"
		:id="context.id"
		v-model="typing"
		:value="context._value"
		:placeholder="context.placeholder"
		class="w-full"
		:loading="loading"
		type="number"
	>
		<template v-slot:append>
			<icon v-if="typing && !loading" name="close" class="text-20px" @click="reset" />
		</template>
	</q-input>
</template>
<script setup>
	import { useTimeoutFn } from '@vueuse/core'

	const props = defineProps({
		context: Object,
	})
	const loadingStore = useLoadingStore()

	const typing = ref('')
	const loading = ref(false)
	const flag = ref(false)

	const {
		start: startError,
		isPending: isPendingError,
		stop: stopError,
	} = useTimeoutFn(
		() => {
			loading.value = false
		},
		5000,
		{ immediate: false },
	)
	const { start, isPending, stop } = useTimeoutFn(
		async () => {
			let value = typing.value
			flag.value = true

			// if (value) {
			// 	loading.value = true
			// }
			await props.context.node.input(value)
			if (props.context.store) {
				props.context.store.collection()
			}
			startError()
		},
		1000,
		{ immediate: false },
	)

	watch(
		() => typing.value,
		() => {
			if (!loading.value) {
				if (!isPending.value) {
					stop()
					stopError()
				}
				start()
			}
		},
	)

	watch(
		() => loadingStore.loading,
		(v) => {
			if (!v) {
				if (isPending) {
					stop()
				}
				if (isPendingError) {
					stopError()
				}
				loading.value = false
				flag.value = false
			} else if (flag.value) {
				loading.value = true
			}
		},
	)

	watch(
		() => props.context.loading,
		() => {
			if (isPending) {
				stop()
			}
			if (isPendingError) {
				stopError()
			}
			loading.value = false
		},
	)

	async function reset() {
		loading.value = false
		typing.value = null
		await props.context.node.input(null)
		if (props.context.store) {
			props.context.store.collection()
		}
	}
</script>
