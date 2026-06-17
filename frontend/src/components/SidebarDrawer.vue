<template>
	<div class="sidebar-wraper" :class="[sidebarStore.position, mode]">
		<div class="sidebar-control" :class="[mode]">
			<div class="close-wraper" @click="sidebarStore.setMode(mode == modeStates.close ? modeStates.prev : modeStates.close)">
				<icon name="close" class="text-20px font-medium" />
			</div>
			<div class="toogle-wraper large" @click="sidebarStore.setMode(mode == modeStates.close ? modeStates.prev : mode == modeStates.large ? modeStates.mini : modeStates.large)">
				<icon fill name="double_arrow" class="text-20px font-medium" />
			</div>
		</div>
		<aside id="sidebar-body" ref="sidebar" class="pt-20px">
			<slot
				name="content"
				:data="{
					mode: sidebarStore.mode,
					modeStates: sidebarStore.modeStates,
				}"
			/>
		</aside>
	</div>
</template>
<script setup lang="ts">
	import { useSidebarStore } from '@/stores/autoimport/sidebar'
	import { useDateFormat, useIntervalFn } from '@vueuse/core'

	interface Props {
		position?: string
		classes?: string
		storeId: string
	}
	const { position = 'left', classes = '', storeId } = defineProps<Props>()

	const sidebarStore = useSidebarStore(storeId, position)
	const { mode, modeStates } = storeToRefs(sidebarStore)
	const mini = computed(() => mode.value == modeStates.value.mini)

	const time: Ref = ref()
	const ampm: Ref = ref()
	const seconds: Ref = ref()
	const toggle: Ref = ref(false)

	function updateDate() {
		time.value = ref(useDateFormat(new Date(), 'hh:mm', { locales: 'es-Es' }).value)
		ampm.value = ref(useDateFormat(new Date(), 'a', { locales: 'es-Es' }).value)
	}
	updateDate()
	useIntervalFn(() => {
		const temp = useDateFormat(new Date(), 'ss', { locales: 'es-Es' }).value
		if (parseInt(temp) % 5 == 0) {
			seconds.value = temp //useDateFormat(new Date(), 'ss', { locales: 'es-Es' }).value
			if (seconds.value == '00') {
				updateDate()
			}
		}
	}, 1000)
</script>
