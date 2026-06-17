<template>
	<div class="relative-position flex flex-center" style="width: 40px; height: 40px; border: 1px solid #ccc; border-radius: 4px; background: #fff;">
		<div class="absolute-top-left q-pa-xs text-caption" style="line-height: 1; z-index: 2;">{{ nro }}</div>
		
		<!-- Base Seat Image Placeholder (grey outline) -->
		<q-icon name="sym_o_airline_seat_recline_normal" size="sm" :color="estado === 'ocupado' ? 'red-8' : (estado === 'libre' ? 'grey-6' : 'orange')" />
		
		<!-- Overlays for 'ocupado' -->
		<q-icon v-if="estado === 'ocupado'" name="sym_o_do_not_disturb" color="red" size="md" class="absolute-center opacity-80" />
		
		<!-- Status indicator box (top right) -->
		<div class="absolute-top-right q-pa-xs" style="line-height: 1; z-index: 2;">
			<q-icon v-if="estado === 'ocupado'" name="sym_o_article" color="white" size="xs" class="bg-red rounded-borders" style="padding: 1px" />
			<q-icon v-else-if="estado === 'libre'" name="sym_o_check_box_outline_blank" color="green" size="xs" />
		</div>
	</div>
</template>

<script setup>
const props = defineProps({
	nro: [Number, String],
	estado: String
})
</script>

<style scoped>
.opacity-80 {
	opacity: 0.8;
}
</style>
