<template>
	<q-input outlined v-model="value" class="w-full" dense :loading="loading" bg-color="white">
		<template v-slot:append>
			<q-icon v-if="!value" name="sym_o_event" class="cursor-pointer" size="20px">
				<q-popup-proxy cover transition-show="scale" transition-hide="scale">
					<q-date minimal v-model="date" v-bind="util.omitKeysContaining(context.node.props)">
						<div class="row items-center justify-end"></div>
					</q-date>
				</q-popup-proxy>
			</q-icon>
			<icon v-else-if="!loading" name="close" size="20px" @click="reset" />
		</template>
	</q-input>
</template>

<script setup lang="ts">
	const props = defineProps({
		context: Object,
	})
	const loadingStore = useLoadingStore()

	const date = ref('')
	const value = ref('')
	const loading = ref(false)

	watch(
		() => date.value,
		(v) => {
			if (!v) {
				value.value = ''
			} else {
				value.value = `${v.from} a ${v.to}`
			}
			save()
		},
	)

	watch(
		() => loadingStore.loading,
		(v) => {
			if (!v) {
				loading.value = false
			}
		},
	)

	async function save() {
		if (!date.value) {
			await props.context.node.input(null)
		} else {
			loading.value = true
			if (props.context?.range) {
				await props.context.node.input([{ after: cformat(date.value.from), before: cformat(date.value.to) }])
			} else {
				await props.context.node.input(date.value)
			}
		}
		if (props.context.store) {
			props.context.store.collection()
		}
	}
	watch(
		() => props.context.clear,
		() => {
			reset()
		},
	)
	async function reset() {
		loading.value = false
		date.value = null
		value.value = null
		await props.context.node.input(null)
		if (props.context.store) {
			props.context.store.collection()
		}
	}
</script>
