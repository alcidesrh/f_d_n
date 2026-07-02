<template>
	<div class="flex flex-center w-full h-full">
		<div id="login" ref="login" class="animate__animated animate__fast">
			<q-card class="q-pa-lg card-login" style="width: 400px; max-width: 90vw" :class="{ 'opacity-50': loading }">
				<div class="text-center w-full mb-15px">
					<div class="text-4rem opacity-80" style="font-family: Faster One">F D N</div>

					<div class="text-1rem opacity-80 font-medium" style="font-weight: 600">Transportes Fuentes del Norte</div>
				</div>

				<q-card-section>
					<FormKit ref="form" type="form" :actions="false" @submit-invalid="shake" @submit="handleSubmit">
						<FormKitSchema :schema="schema" :data="data" />
						<div v-if="error" class="flex gap-1 items-center">
							<icon name="error" class="text-red-6 font-300" />
							<FormKitMessages />
						</div>
					</FormKit>
				</q-card-section>
				<div class="flex justify-center gap-4">
					<img v-for="name in ['lapionera', 'rosita', 'mayadeoro', 'starbus', 'corporacionlapionera']" :src="`images/logos/copiloto/${name}5.png`" width="100px" class="logo" />
				</div>
			</q-card>
		</div>
		<div id="layout-login2"></div>
		<div ref="layer0" class="bg-layer" :style="{ backgroundImage: `url('images/login${a}.png')` }"></div>
		<div ref="layer1" class="bg-layer" :style="{ backgroundImage: `url('images/login${b}.png')`, opacity: 0 }"></div>
	</div>
</template>

<script lang="ts" setup>
	import { useUserSessionStore } from '@/stores/autoimport/session'
	import { FormKitMessages } from '@formkit/vue'
	import { gsap } from 'gsap'
	import { ref } from 'vue'

	const INTERVAL_MS = 5000
	const CROSSFADE_MS = 1

	const form = useTemplateRef('form')
	const card = useTemplateRef('login')
	const layer0 = useTemplateRef<HTMLElement>('layer0')
	const layer1 = useTemplateRef<HTMLElement>('layer1')
	const error = ref(false)
	const loadingStore = useLoadingStore()
	const { loading } = storeToRefs(loadingStore)

	const start = Math.floor(Math.random() * 10) + 1
	const a = ref(start)
	const b = ref((start % 10) + 1)
	let activeIsA = true

	let intervalId: ReturnType<typeof setInterval> | null = null
	let tween: gsap.core.Tween | null = null

	function preload(src: string) {
		const img = new Image()
		img.src = src
	}

	function advance() {
		const from = activeIsA ? layer0.value : layer1.value
		const to = activeIsA ? layer1.value : layer0.value
		const current = activeIsA ? a.value : b.value
		const next = (current % 10) + 1

		if (activeIsA) {
			b.value = next
		} else {
			a.value = next
		}

		to!.style.opacity = '0'

		preload(`images/login${(next % 10) + 1}.png`)

		tween = gsap.to(from, {
			opacity: 0,
			duration: CROSSFADE_MS,
			ease: 'power2.inOut',
		})
		gsap.to(to, {
			opacity: 1,
			duration: CROSSFADE_MS,
			ease: 'power2.inOut',
			onComplete: () => {
				activeIsA = !activeIsA
				from!.style.opacity = '0'
			},
		})
	}

	onMounted(() => {
		const second = activeIsA ? b.value : a.value
		preload(`images/login${second}.png`)
		preload(`images/login${(second % 10) + 1}.png`)
		intervalId = setInterval(advance, INTERVAL_MS)
		card.value.addEventListener('animationend', removeAnimation)
	})

	onBeforeUnmount(() => {
		if (intervalId) clearInterval(intervalId)
		if (tween) tween.kill()
		gsap.killTweensOf(layer0.value)
		gsap.killTweensOf(layer1.value)
		card.value.removeEventListener('animationend', removeAnimation)
	})

	const schema = [
		{
			$el: 'div',
			attrs: {
				class: 'grid gap-20px',
			},
			children: [
				{
					$formkit: 'text_icon',
					name: 'username',
					labelQuasar: 'Usuario',
					validation: 'required',
					prepend: 'person',
				},
				{
					$formkit: 'password',
					name: 'password',
					validation: 'required',
				},
			],
		},

		{
			$formkit: 'button',
			loading: '$loading',
			binds: {
				label: 'Aceptar',
				type: 'button',
				class: 'full-width u-mt-s ',
				onClick: '$submit',
			},
		},
	]

	const submit = () => {
		form.value.node.submit()
		if (!form.value.node.context.state.valid) {
			shake()
		}
	}
	const data = ref({ submit, loading: computed(() => loading && loadingStore.isOpLoading('login')) })

	async function handleSubmit(credentials: Record<string, string>, node: Record<any, any>) {
		const restApi = await useApi()

		error.value = false
		node.clearErrors()
		restApi
			.post('/login', credentials, { key: 'login' })
			.then(async (resp) => {
				const store = useUserSessionStore()
				const schemaStore = useSchemaStore()
				await schemaStore.loadEntities()
				store.user = resp.username
				store.permissions = resp.permissions
				store.token = resp.token
				const router = useRouter()
				router.push({ path: store.redirectTo })
			})
			.catch((e: FetchError | string) => {
				console.log(e)
				error.value = true
				node.setErrors([e])
				shake()
			})
	}
	function shake() {
		card.value.classList.add('animate__shakeX')
	}

	function removeAnimation() {
		card.value.classList.remove('animate__shakeX')
	}
</script>
<style scoped lang="scss">
	img {
		&.logo {
			opacity: 0.4;
		}
	}
	#login {
		& > .card-login {
			box-shadow: 0px 0px 18px 0px $surface-8;
		}
		z-index: 3;
		background-color: -alpha($surface-1, 0.7);
		& > div {
			background-color: transparent;
		}
	}
	#layout-login2 {
		width: 100vw;
		height: 100vh;
		position: absolute;
		z-index: 2;
		// background-color: -alpha($surface-1, 0.4);
	}
	.bg-layer {
		filter: blur(10px);
		position: absolute;
		width: 100vw;
		min-height: 100vh;
		z-index: 1;
		background-repeat: no-repeat;
		background-position: center;
		background-size: cover;
		background-attachment: fixed;
		margin: 0;
	}
</style>
