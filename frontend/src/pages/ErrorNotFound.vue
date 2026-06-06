<template>
	<div class="fullscreen text-center q-pa-md flex flex-center">
		<div>
			<!-- <div class="text-4xl q-mb-md" style="opacity: 0.5">404</div> -->
			<div class="text-8xl q-mb-lg">🧭</div>
			<div class="m-auto mb-20px text-h4">¡Ala gran! ¡Vos! Aquí no esta la página que busca</div>
			<div class="m-auto text-h5" style="opacity: 1; max-width: 480px">Se nos fue como vuelto de tienda. No sabemos dónde quedó, pero puedes empezar de nuevo la ruta.</div>
			<div v-if="action && entity" class="q-m-auto q-mt-lg text-body1 bg-white/10 rounded-lg px-4 py-2 inline-block">La página para {{ action }} {{ entity }} no existe.</div>
			<div class="flex flex-center q-gap-sm q-mt-xl">
				<q-btn color="white" text-color="blue" icon="sym_o_home" unelevated to="/" label="Ir al inicio" no-caps class="text-lg" />
				<q-btn text-slate-3 icon="sym_o_arrow_back" flat unelevated text-color="blue" class="text-lg" @click="router.back()" label="Volver atrás" no-caps />
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
	import { computed } from 'vue'

	const router = useRouter()
	const route = useRoute()

	const action = computed(() => route.params.action as string | undefined)
	const entity = computed(() => route.params.entity as string | undefined)
</script>
<style lang="css" scoped>
	.fullscreen {
		background-color: $background;
	}
</style>
