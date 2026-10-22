<!--
  Acción de fila «Accesos» del listado de usuarios (`listActions`): lleva a
  sus roles y alcance (`/configuracion/usuarios/:id/accesos`, ADR-039). Es
  una acción de fila como las demás; en vez de un diálogo, navega.
-->
<template><span hidden /></template>

<script setup lang="ts">
import { watch } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps<{ item: unknown; visible: boolean }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean] }>()
const router = useRouter()

watch(
  () => props.visible,
  (v) => {
    if (!v) return
    emit('update:visible', false)
    void router.push({ name: 'acceso-usuario', params: { id: String((props.item as { id?: unknown }).id ?? '').split('/').pop() } })
  },
  { immediate: true },
)
</script>
