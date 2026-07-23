<template>
	<div class="topbar">
		<div class="topbar-content px-2rem">
			<nav class="left-section left-content"></nav>

			<div class="center-section center-content">
				<Breadcrumbs />
			</div>

			<nav class="right-section right-content">
				<Icon class="cursor-pointer" name="logout" @click="logout" />
			</nav>
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
