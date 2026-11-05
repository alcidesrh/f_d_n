<!-- Acción de fila «Bitácora»: la línea de tiempo del registro. -->
<template>
  <BitacoraDialog :tipo="tipo" :id="id" :visible="visible" @update:visible="emit('update:visible', $event)" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import BitacoraDialog from '@/shared/bitacora/BitacoraDialog.vue'
import { idDisplay } from './listUtils'

const props = defineProps<{ item: unknown; visible: boolean; tipo: 'boleto' | 'salida' }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean] }>()

const id = computed(() => Number(idDisplay((props.item as { id?: unknown }).id)))
</script>
