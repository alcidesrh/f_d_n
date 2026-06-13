<template>
	<span id="clock">
		<span class="datetime" @click="sidebarStore.setMode(mode == modeStates.close ? modeStates.prev : mode == modeStates.large ? modeStates.mini : modeStates.large)">
			<span>{{ time }}</span>
			<span class="text-12px mt-5px">{{ seconds }}</span>
			<span>{{ ampm }}</span>
		</span>

		<div class="logo-fdn">FDN</div>
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
<style scoped lang="scss">
	#clock {
		// background-color: $topbar-sidebar-bg;
		display: flex;
		width: $sidebar-width; //calc($sidebar-width + 30px);
		height: $topbar;
		align-items: center;
		justify-content: center;
		overflow: hidden;
		position: fixed;
		left: 0;

		& > .datetime {
			cursor: pointer;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 1.4rem;
			position: absolute;
			color: $surface-7;
			font-weight: 700;
			letter-spacing: 2px;
			z-index: 10;
			// background-color: -alpha($topbar-sidebar-bg, .5); // $topbar-sidebar-bg;
			// box-shadow: 0px 0px 4px 2px $surface-5;
			// padding: 0px 8px;
			// left: 50%;
			// margin-left: -59px;
			// top: 50%;
			// margin-top: -13px;
			// border-radius: 4px;
		}

		& > .logo-fdn {
			position: absolute;
			color: -alpha($surface-3, 1);
			font-size: 35px;
			letter-spacing: 15px;
			font-family: 'diplomata-sc-regular';
			transform: scaleY(2);
		}
	}
</style>
