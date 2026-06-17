<template>
	<div>
		<div class="topbar px-2rem">
			<div class="left-section">
				<clock />
			</div>

			<div class="center-section">
				<Breadcrumbs />
			</div>

			<div class="right-section">
				<Icon class="cursor-pointer" name="dashboard" @click="useRouter().router.push({ name: 'home' })" />
				<Icon class="cursor-pointer" name="logout" @click="logout" />
			</div>
		</div>
		<div id="intersectionObservertarget" class="absolute" />
	</div>
</template>
<script setup lang="ts">
	async function logout() {
		const restApi = useApi()
		restApi.post('/logout').then(async (resp) => {
			useUserSessionStore().clear()
			const router = useRouter()
			router.push({ path: '/login' })
		})
	}
	const observer = new IntersectionObserver(
		(e) => {
			const el = document.querySelector('.topbar')
			if (e[0].intersectionRatio < 1) el.classList.add('layout-topbar-sticky')
			else el.classList.remove('layout-topbar-sticky')
		},
		{
			threshold: 1,
			// box-shadow: $shadow-3;
		},
	)

	onMounted(async () => {
		const el = document.querySelector('#intersectionObservertarget')
		observer.observe(el)
	})
	onUnmounted(() => observer.disconnect())
</script>

<style scoped lang="scss">
	.right-section {
		& > .fdn-icon {
			font-size: 24px;
			// font-weight: 400;
			color: $surface-6;
		}
	}
</style>
