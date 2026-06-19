<template>
	<div class="topbar">
		<div class="topbar-content px-2rem">
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
</script>

<style scoped lang="scss">
	.right-section {
		display: flex;
		& > .fdn-icon {
			margin: 0px 10px;
			font-size: 24px;
			// font-weight: 400;
			color: $surface-6;
		}
	}
</style>
