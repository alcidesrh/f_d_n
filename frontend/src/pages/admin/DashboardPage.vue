<template>
	<div class="dashboard">
		<div class="dashboard__header">
			<div>
				<h1 class="dashboard__title">Panel de Administración</h1>
				<p class="dashboard__subtitle">Gestión y monitoreo del sistema de transporte</p>
			</div>
			<div class="dashboard__date">
				<Icon name="calendar_today" />
				{{ today }}
			</div>
		</div>
		<div class="accordion">
			<article class="item">
				<button class="header">
					<span>Resumen</span>
					<svg class="icon" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none" />
					</svg>
				</button>

				<div class="body">
					<div class="content">
						<div class="stats-grid">
							<Stats />
						</div>
					</div>
				</div>
			</article>

			<article class="item active">
				<button class="header">
					<span>Gestión de Entidades</span>

					<svg class="icon" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none" />
					</svg>
				</button>
				<div class="body">
					<EntityList />
				</div>
			</article>
		</div>
	</div>
</template>

<script setup lang="ts">
const today = computed(() =>
	new Date().toLocaleDateString('es-BO', {
		weekday: 'long',
		year: 'numeric',
		month: 'long',
		day: 'numeric',
	}),
)
const recordCounts = ref<Record<string, number>>({})
onMounted(async () => {
	try {
		// gsap.value.registerPlugin()

		const items = document.querySelectorAll('.item')

		items.forEach((item) => {
			const header = item.querySelector('.header')
			const body = item.querySelector('.body')
			const icon = item.querySelector('.icon')

			if (item.classList.contains('active')) {
				gsap.value.set(body, {
					height: 'auto',
				})

				gsap.value.set(icon, {
					rotate: 45,
				})

				// gsap.value.set(item, {
				// 	boxShadow: '0 4px 4px -1px oklch(92.9% 0.013 255.508deg);',
				// })
			}

			header.addEventListener('click', () => {
				const opened = item.classList.contains('active')

				// items.forEach(closeItem)

				if (!opened) {
					openItem(item)
				} else {
					closeItem(item)
				}
			})
		})

		function openItem(item) {
			const body = item.querySelector('.body')
			const icon = item.querySelector('.icon')

			item.classList.add('active')

			gsap.value
				.timeline({
					defaults: {
						ease: 'power3.out',
					},
				})

				// .to(
				// 	item,
				// 	{
				// 		boxShadow: '0 4px 4px -1px oklch(92.9% 0.013 255.508deg);',
				// 		duration: 0.35,
				// 	},
				// 	0,
				// )

				.to(
					icon,
					{
						rotate: 45,
						duration: 0.45,
						ease: 'back.out(2)',
					},
					0,
				)

				.to(
					body,
					{
						height: 'auto',
						duration: 0.55,
						ease: 'expo.out',
					},
					0,
				)

				.fromTo(
					body.firstElementChild,
					{
						opacity: 0,
						y: -16,
					},
					{
						opacity: 1,
						y: 0,
						duration: 0.35,
						ease: 'power2.out',
					},
					'-=.25',
				)
		}

		function closeItem(item) {
			if (!item.classList.contains('active')) return

			item.classList.remove('active')

			const body = item.querySelector('.body')
			const icon = item.querySelector('.icon')

			gsap.value
				.timeline()

				.to(body.firstElementChild, {
					opacity: 0,
					y: -12,
					duration: 0.18,
				})

				.to(
					body,
					{
						height: 0,
						duration: 0.42,
						ease: 'expo.inOut',
					},
					'<',
				)

				.to(
					icon,
					{
						rotate: 0,
						duration: 0.3,
					},
					'<',
				)

			// .to(
			// 	item,
			// 	{
			// 		boxShadow: '0 4px 4px -1px oklch(92.9% 0.013 255.508deg);',
			// 		duration: 0.3,
			// 	},
			// 	'<',
			// )
		}
	} catch {
		// silently fail; cards show placeholder
	} finally {
	}
})
</script>

<style lang="scss">
.dashboard {
	padding: 1.5rem;

	&__header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 1rem;
		margin-bottom: 2rem;
		flex-wrap: wrap;
	}

	&__title {
		font-size: 1.625rem;
		font-weight: 700;
		// color: $dark;
		color: $surface-6;
		margin: 0;
		line-height: 1.3;
	}

	&__subtitle {
		font-size: 0.9rem;
		color: $surface-6;
		margin: 0.25rem 0 0;
	}

	&__date {
		display: flex;
		align-items: center;
		gap: 0.4rem;
		font-size: 0.875rem;
		color: $surface-6;
		white-space: nowrap;
		padding-top: 0.25rem;
	}
}
.accordion {
	// width: min(700px, 95%);
	margin: 60px auto;

	& > .item {
		overflow: hidden;

		border-radius: 16px;

		margin-bottom: 2rem;

		background: white;
		// border: 1px solid #e7ebf2;
		border: $border;
		border-radius: 10px;
		// box-shadow: 0 4px 4px -1px $neutral-3;

		& > .header {
			width: 100%;
			padding: 22px 26px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			cursor: pointer;
			border: none;
			background: $surface-1;
			font-size: 18px;
			font-weight: 600;
			& > .icon {
				width: 20px;
				height: 20px;
			}
			&:hover {
				background: $surface-2;
			}
		}
		& > .body {
			height: 0;
			overflow: hidden;
			& > .content {
				padding: 2rem;
				display: grid;
				grid-template-rows: 1fr;
				transition: grid-template-rows 1s ease-out;
				overflow: hidden;
				// min-height: 0;
				& > .stats-grid {
					display: grid;
					grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
					gap: 1rem;
				}

				& > .entities-grid {
					display: grid;
					grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
					gap: 1rem;
				}
				& > .empty {
					display: flex;
					align-items: center;
					gap: 1rem;
					justify-content: center;
					padding: 3rem;
					color: $surface-6;
					font-size: 0.9rem;
				}
			}
		}
	}
}
@media (max-width: 599px) {
	.dashboard {
		padding: 1rem;
	}
	.accordion > .item > .header {
		flex-direction: column;
	}
	.accordion > .item > .body > .content > .stats-grid {
		grid-template-columns: 1fr;
	}

	.accordion > .item > .body > .content > .entities-grid {
		grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
	}
}
</style>
