<template>
	<div class="flex flex-center w-full h-full">
		<div id="login" ref="login" class="animate__animated animate__fast">
			<q-card class="q-pa-lg shadow-12" style="width: 400px; max-width: 90vw" :class="{ 'opacity-50': loading }">
				<div class="text-center w-full mb-15px">
					<div class="text-4rem opacity-80 text-zinc-1" style="font-family: Faster One">F D N</div>

					<div class="text-1rem opacity-80 font-medium" style="font-weight: 600">Transportes Fuentes del Norte</div>
				</div>

				<!-- </q-card-section> -->

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
		<div id="layout-login" :style="{ backgroundImage: `url('images/login${img}.png')` }"></div>
	</div>
</template>

<script lang="ts" setup>
	import { useUserSessionStore } from '@/stores/autoimport/session'
	import { FormKitMessages } from '@formkit/vue'
	import { ref } from 'vue'
	const img = computed(() => Math.floor(Math.random() * 10) + 1)
	const form = useTemplateRef('form')
	const card = useTemplateRef('login')
	const error = ref(false)
	const loadingStore = useLoadingStore()
	const { loading } = storeToRefs(loadingStore)
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
	onMounted(() => {
		card.value.addEventListener('animationend', removeAnimation)
	})
	onBeforeUnmount(() => {
		card.value.removeEventListener('animationend', removeAnimation)
	})
</script>
<style scoped lang="scss">
	img {
		// opacity: 0.5;
		&.logo {
			opacity: 0.4;
		}
		// border-radius: 9px;
		// box-shadow: $shadow-10;
	}
	#login {
		z-index: 3;
		background-color: white; //-alpha($surface-3, 0.3);
		// background: #64748b;
		background: linear-gradient(180deg, rgba(100, 116, 139, 0.51) 0%, rgba(249, 250, 251, 1) 99%);
		& > div {
			background-color: transparent;
		}
	}
	#layout-login2 {
		width: 100vw;
		height: 100vh;
		// background-color: -alpha($neutral-8, 0.5);
		position: absolute;
		z-index: 2;
	}
	#layout-login {
		filter: blur(10px);
		position: absolute;
		width: 100vw;
		z-index: 1;
		/* Prevents the image from repeating like a tile grid */
		background-repeat: no-repeat;
		/* Centers the image horizontally and vertically */
		background-position: center;
		/* Scales the image to completely cover the entire screen */
		background-size: cover;
		/* Makes the image stick in place while scrolling content */
		background-attachment: fixed;
		/* Ensures the body takes up at least the full window height */
		min-height: 100vh;
		margin: 0;
	}
</style>
