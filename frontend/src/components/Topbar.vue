<template>
	<div>
		<div class="topbar">
			<div class="left-section">
				<clock />
			</div>

			<div class="center-section">
				<Breadcrumbs />
			</div>

			<div class="right-section">
				<clock />
			</div>
		</div>
		<div id="intersectionObservertarget" class="absolute" />
	</div>
</template>
<script setup lang="ts">
	// const breadcrumb = useBreadcrumbs()
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
