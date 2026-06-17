<template>
	<div class="fullscreen text-center q-pa-md flex flex-center">
		<div>
			<div class="text-8xl q-mb-lg">🔒</div>
			<div class="m-auto mb-20px text-h4">Acceso denegado</div>
			<div class="m-auto text-h5" style="opacity: 1; max-width: 480px">No tienes los permisos necesarios para acceder a esta sección. Si crees que esto es un error, contacta al administrador del sistema.</div>
			<div class="flex flex-center q-gap-sm q-mt-xl">
				<q-btn color="white" text-color="blue" icon="sym_o_dashboard" unelevated to="/" label="Ir al inicio" no-caps class="text-lg" />
				<q-btn text-slate-3 icon="sym_o_arrow_back" flat unelevated text-color="blue" class="text-lg" @click="router.back()" label="Volver atrás" no-caps />
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
	const router = useRouter()
</script>
<style lang="scss" scoped>
	.fullscreen {
		background-color: $background;
	}
</style>
