<!-- Acción de fila «Bitácora» del listado de boletos. -->
<template>
  <BitacoraDialog tipo="boleto" :id="id" :visible="visible" @update:visible="emit('update:visible', $event)" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import BitacoraDialog from '@/shared/bitacora/BitacoraDialog.vue'
import { idDisplay } from './listUtils'

const props = defineProps<{ item: unknown; visible: boolean }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean] }>()

const id = computed(() => Number(idDisplay((props.item as { id?: unknown }).id)))
</script>
