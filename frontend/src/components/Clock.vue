<template>
	<span id="clock">
		<div class="logo-fdn">
			<span>F</span>
			<span>D</span>
			<span>N</span>
		</div>
		<span class="datetime" @click="sidebarStore.setMode(mode == modeStates.close ? modeStates.prev : mode == modeStates.large ? modeStates.mini : modeStates.large)">
			<span>{{ time }}</span>
			<span class="text-10px mt-7px mx-3px">{{ seconds }}</span>
			<span class="font-semibold">{{ ampm }}</span>
		</span>
	</span>
</template>
<script setup lang="ts">
import { useDateFormat, useIntervalFn } from '@vueuse/core'

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
