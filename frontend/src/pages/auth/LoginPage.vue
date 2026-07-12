<template>
	<div class="flex flex-center w-full h-full">
		<!-- <img
			v-for="name in ['lapionera', 'rosita', 'mayadeoro', 'starbus', 'corporacionlapionera']"
			:src="`images/logos/copiloto/${name}5.png`"
			width="100px"
			class="logo"
			:style="{ position: 'absolute', top: `${random(0, 100)}vh`, left: `${random(0, 100)}vw` }"
		/> -->
		<div class="background">
			<div class="img" v-for="(image, index) in backgroundImages" :key="index" :style="{ backgroundImage: `url('${image.src}')` }"></div>
			<!-- <img v-for="(image, index) in backgroundImages" :key="index" :src="image.src" alt="" /> -->
		</div>
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
				<div class="card-carpet"></div>
			</q-card>
		</div>
		<div id="layout-login2"></div>
		<div ref="layer0" class="bg-layer" :style="{ backgroundImage: `url('images/login${a}.png')` }"></div>
		<div ref="layer1" class="bg-layer" :style="{ backgroundImage: `url('images/login${b}.png')` }"></div>
	</div>
</template>

<script lang="ts" setup>
import { useUserSessionStore } from '@/stores/autoimport/session'
import { FormKitMessages } from '@formkit/vue'
import { gsap } from 'gsap'
import { ref } from 'vue'
import { router } from '@/router'
const INTERVAL_MS = 5000

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
let tween: gsap.core.Timeline | null = null
let currentBgClass = ''

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

	preload(`images/login${(next % 10) + 1}.png`)

	gsap.set(from, { clearProps: 'transform' })
	gsap.set(to, { opacity: 0, clearProps: 'transform' })

	const tl = gsap.timeline({
		onComplete: () => {
			activeIsA = !activeIsA
			gsap.set(from, { opacity: 0, clearProps: 'transform' })

			const imgNum = activeIsA ? a.value : b.value
			const newClass = `login${imgNum}`
			balls.forEach((ball) => {
				if (ball.el) {
					ball.el.classList.remove(currentBgClass)
					ball.el.classList.add(newClass)
				}
			})
			currentBgClass = newClass
		},
	})

	tl.to(
		from,
		{
			y: -280,
			rotation: 5,
			scale: 0.95,
			opacity: 0,
			duration: 0.4,
			ease: 'back.in(1.7)',
		},
		0,
	)

	tl.to(
		to,
		{
			opacity: 1,
			duration: 0.25,
			ease: 'power2.out',
		},
		0.05,
	)

	tl.call(() => moveImages(), [], 0.12)

	tween = tl
}

// ── Physics constants ──
const FRICTION = 0.991
const WALL_RESTITUTION = 0.75
const BALL_RESTITUTION = 0.92
const IMPULSE_SPEED_MIN = 12
const IMPULSE_SPEED_MAX = 24

interface Ball {
	x: number
	y: number
	vx: number
	vy: number
	radius: number
	rotation: number
	rotationSpeed: number
	el: HTMLElement | null
}

const balls: Ball[] = []
let animFrameId: number | null = null

// ── Elastic collision between two circles ──
function resolveBallCollision(a: Ball, b: Ball) {
	const dx = b.x - a.x
	const dy = b.y - a.y
	const dist = Math.sqrt(dx * dx + dy * dy)
	const minDist = a.radius + b.radius

	if (dist >= minDist || dist === 0) return

	// Unit normal & tangent
	const nx = dx / dist
	const ny = dy / dist

	// Separate overlapping balls
	const overlap = (minDist - dist) / 2
	a.x -= nx * overlap
	a.y -= ny * overlap
	b.x += nx * overlap
	b.y += ny * overlap

	// Relative velocity along normal
	const dvx = a.vx - b.vx
	const dvy = a.vy - b.vy
	const dvDotN = dvx * nx + dvy * ny

	// Only resolve if approaching
	if (dvDotN <= 0) return

	// Equal-mass elastic impulse
	const impulse = dvDotN * BALL_RESTITUTION
	a.vx -= impulse * nx
	a.vy -= impulse * ny
	b.vx += impulse * nx
	b.vy += impulse * ny

	// Collision-induced spin (tangential component)
	const tangentSpeed = dvx * -ny + dvy * nx
	a.rotationSpeed += tangentSpeed * 0.06
	b.rotationSpeed -= tangentSpeed * 0.06
}

// ── Circle vs rectangle (login card) collision ──
function resolveRectCollision(ball: Ball, rect: DOMRect) {
	const closestX = Math.max(rect.left, Math.min(ball.x, rect.right))
	const closestY = Math.max(rect.top, Math.min(ball.y, rect.bottom))

	const dx = ball.x - closestX
	const dy = ball.y - closestY
	const distSq = dx * dx + dy * dy

	if (distSq >= ball.radius * ball.radius || distSq === 0) return

	const dist = Math.sqrt(distSq)
	const nx = dx / dist
	const ny = dy / dist
	const overlap = ball.radius - dist

	ball.x += nx * overlap
	ball.y += ny * overlap

	const vDotN = ball.vx * nx + ball.vy * ny
	if (vDotN < 0) {
		ball.vx -= (1 + WALL_RESTITUTION) * vDotN * nx
		ball.vy -= (1 + WALL_RESTITUTION) * vDotN * ny
	}

	ball.rotationSpeed += (ball.vy * nx - ball.vx * ny) * 0.03
}

// ── Physics loop (requestAnimationFrame) ──
function startPhysicsLoop() {
	let lastTime = performance.now()

	function step(now: number) {
		const rawDt = (now - lastTime) / 16.667 // normalise to ~60 fps
		const dt = Math.min(rawDt, 3) // cap so tab-switch doesn't explode
		lastTime = now

		const W = window.innerWidth
		const H = window.innerHeight
		const loginRect = card.value?.getBoundingClientRect()

		// Integrate velocities
		for (const ball of balls) {
			const f = Math.pow(FRICTION, dt)
			ball.vx *= f
			ball.vy *= f
			ball.rotationSpeed *= f

			ball.x += ball.vx * dt
			ball.y += ball.vy * dt
			ball.rotation += ball.rotationSpeed * dt

			// ── Wall bounces ──
			if (ball.x - ball.radius < 0) {
				ball.x = ball.radius
				ball.vx = Math.abs(ball.vx) * WALL_RESTITUTION
				ball.rotationSpeed += ball.vy * 0.02
			} else if (ball.x + ball.radius > W) {
				ball.x = W - ball.radius
				ball.vx = -Math.abs(ball.vx) * WALL_RESTITUTION
				ball.rotationSpeed -= ball.vy * 0.02
			}

			if (ball.y - ball.radius < 0) {
				ball.y = ball.radius
				ball.vy = Math.abs(ball.vy) * WALL_RESTITUTION
				ball.rotationSpeed -= ball.vx * 0.02
			} else if (ball.y + ball.radius > H) {
				ball.y = H - ball.radius
				ball.vy = -Math.abs(ball.vy) * WALL_RESTITUTION
				ball.rotationSpeed += ball.vx * 0.02
			}

			// ── Login card collision (balls bounce off the form) ──
			if (loginRect) resolveRectCollision(ball, loginRect)
		}

		// Ball-ball collisions (O(n²) is fine for 5 elements)
		for (let i = 0; i < balls.length; i++) {
			for (let j = i + 1; j < balls.length; j++) {
				resolveBallCollision(balls[i], balls[j])
			}
		}

		// Render to DOM
		for (const ball of balls) {
			if (!ball.el) continue
			ball.el.style.left = `${ball.x}px`
			ball.el.style.top = `${ball.y}px`
			ball.el.style.transform = `translate(-50%, -50%) rotate(${ball.rotation}deg)`
		}

		animFrameId = requestAnimationFrame(step)
	}

	animFrameId = requestAnimationFrame(step)
}

// ── Give each ball an impulse toward a random grid cell ──
function moveImages() {
	const cols = 4
	const rows = 3
	const cells: { x: number; y: number }[] = []

	for (let y = 0; y < rows; y++) {
		for (let x = 0; x < cols; x++) {
			cells.push({ x, y })
		}
	}
	shuffle(cells)

	const W = window.innerWidth
	const H = window.innerHeight

	balls.forEach((ball, index) => {
		const cell = cells[index % cells.length]
		const targetX = (cell.x + Math.random()) * (W / cols)
		const targetY = (cell.y + Math.random()) * (H / rows)

		const dx = targetX - ball.x
		const dy = targetY - ball.y
		const dist = Math.sqrt(dx * dx + dy * dy)

		if (dist > 0) {
			const speed = random(IMPULSE_SPEED_MIN, IMPULSE_SPEED_MAX)
			ball.vx = (dx / dist) * speed
			ball.vy = (dy / dist) * speed
		}

		// Random spin on each hit
		ball.rotationSpeed = random(-4, 4)
	})
}

// ── Image list ──
const images = [
	'images/logos/copiloto/lapionera5.png',
	'images/logos/copiloto/rosita5.png',
	'images/logos/copiloto/mayadeoro5.png',
	'images/logos/copiloto/starbus5.png',
	'images/logos/copiloto/corporacionlapionera5.png',
]

const backgroundImages = ref<{ src: string }[]>([])

// ── Initial layout + start physics ──
function generateLayout() {
	const cols = 4
	const rows = 3
	const cells: { x: number; y: number }[] = []

	for (let y = 0; y < rows; y++) {
		for (let x = 0; x < cols; x++) {
			cells.push({ x, y })
		}
	}
	shuffle(cells)

	backgroundImages.value = images.map((src) => ({ src }))

	nextTick(() => {
		const W = window.innerWidth
		const H = window.innerHeight
		const imgElements = document.querySelectorAll('.background .img')

		const imgNum = activeIsA ? a.value : b.value
		currentBgClass = `login${imgNum}`

		balls.length = 0
		imgElements.forEach((el, index) => {
			const cell = cells[index % cells.length]
			const x = (cell.x + Math.random()) * (W / cols)
			const y = (cell.y + Math.random()) * (H / rows)
			const width = 150 //random(150, 250)
			const height = 150 //random(150, 250)
			const rotation = random(-25, 25)

			const htmlEl = el as HTMLElement
			htmlEl.classList.add(currentBgClass)
			htmlEl.style.left = `${x}px`
			htmlEl.style.top = `${y}px`
			htmlEl.style.width = `${width}px`
			htmlEl.style.height = `${height}px`
			htmlEl.style.transform = `translate(-50%, -50%) rotate(${rotation}deg)`

			balls.push({
				x,
				y,
				vx: 0,
				vy: 0,
				radius: width / 2,
				rotation,
				rotationSpeed: 0,
				el: htmlEl,
			})
		})

		startPhysicsLoop()
	})
}

function random(min: number, max: number) {
	return Math.random() * (max - min) + min
}

function shuffle<T>(array: T[]) {
	for (let i = array.length - 1; i > 0; i--) {
		const j = Math.floor(Math.random() * (i + 1))
		;[array[i], array[j]] = [array[j], array[i]]
	}
}

onMounted(() => {
	const second = activeIsA ? b.value : a.value
	preload(`images/login${second}.png`)
	preload(`images/login${(second % 10) + 1}.png`)
	intervalId = setInterval(advance, INTERVAL_MS)
	card.value.addEventListener('animationend', removeAnimation)
	layer1.value!.style.opacity = '0'
	generateLayout()
})

onBeforeUnmount(() => {
	if (intervalId) clearInterval(intervalId)
	if (tween) tween.kill()
	if (animFrameId) cancelAnimationFrame(animFrameId)
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
			// const router = useRouter()
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
#login {
	& > .card-login {
		box-shadow: 0px 0px 18px 0px $surface-8;
	}
	z-index: 4;
	background-color: -alpha($surface-1, 0.6);
	backdrop-filter: blur(5px);
	& > div {
		background-color: transparent;
	}
}
#layout-login2 {
	width: 100vw;
	height: 100vh;
	position: absolute;
	z-index: 2;

	// background-color: -alpha($surface-7, 0.4);
}
.bg-layer {
	filter: blur(5px);
	position: absolute;
	width: 100vw;
	min-height: 100vh;
	z-index: 1;
	background-repeat: no-repeat;
	background-position: center;
	background-size: cover;
	background-attachment: fixed;
	margin: 0;
	will-change: transform, opacity;
}

.background {
	position: fixed;
	inset: 0;
	overflow: hidden;
	pointer-events: none;
	z-index: 3;
	padding: 30px;
}

.background .img {
	// transition: border 2s ease-in-out;
	border: 20px solid $surface-1;
	// background-color: $surface-2;
	background-color: -alpha($surface-1, 0.5);
	backdrop-filter: blur(8px);
	z-index: 9999;
	border-radius: 999px;
	width: 50px;
	height: 50px;
	padding: 20px;
	position: absolute;
	user-select: none;
	// opacity: 0.4;
	// width: 100vw;
	// min-height: 100vh;
	z-index: 1;
	background-repeat: no-repeat;
	background-position: center;
	background-size: contain;
	// background-attachment: fixed;
	margin: 0;
	&.login2,
	&.login9,
	&.login1,
	&.login8,
	&.login3 {
		border: 20px solid $surface-9;
		background-color: -alpha($surface-1, 0.8);
	}
	&:nth-child(1) {
		transition:
			border 0.2s 0.5s,
			background-color 0.2s 0.5s;
		// transition-delay: 0.1s;
	}
	&:nth-child(2) {
		transition:
			border 0.2s 0.7s,
			background-color 0.2s 0.7s;
		// transition-delay: 0..5s;
	}
	&:nth-child(3) {
		transition:
			border 0.2s 0.9s,
			background-color 0.2s 0.9s;
		// transition-delay: 0.3s;
	}
	&:nth-child(4) {
		transition:
			border 0.2s 1.12s,
			background-color 0.2s 1.12s;
		// transition-delay: 0.4s;
	}
	&:nth-child(5) {
		transition:
			border 0.2s 1.24s,
			background-color 0.2s 1.24s;
		// transition-delay: 0.5s;
	}
}
</style>
