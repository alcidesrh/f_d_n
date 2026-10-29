<!-- Visor de fotos del chat: pantalla completa, flechas (teclado y botones), descarga. -->
<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="fotos.length" class="visor" role="dialog" aria-modal="true" :aria-label="actual?.nombre" @click.self="emit('cerrar')" @keydown="tecla" tabindex="-1" ref="raiz">
        <header class="visor__barra">
          <span class="truncate">{{ actual?.nombre }}<template v-if="fotos.length > 1"> · {{ indice + 1 }}/{{ fotos.length }}</template></span>
          <span class="flex gap-1">
            <a v-if="actual" :href="urlArchivo(actual, true)" :download="actual.nombre" class="visor__boton tap-target" aria-label="Descargar"><icon name="download" color="text-current" /></a>
            <button type="button" class="visor__boton tap-target" aria-label="Cerrar" @click="emit('cerrar')"><icon name="close" color="text-current" /></button>
          </span>
        </header>
        <img v-if="actual" :key="actual.id" :src="urlArchivo(actual)" :alt="actual.nombre" class="visor__imagen" />
        <template v-if="fotos.length > 1">
          <button type="button" class="visor__boton visor__nav visor__nav--izq" aria-label="Anterior" @click="mover(-1)"><icon name="chevron-left" size="1.75rem" color="text-current" /></button>
          <button type="button" class="visor__boton visor__nav visor__nav--der" aria-label="Siguiente" @click="mover(1)"><icon name="chevron-right" size="1.75rem" color="text-current" /></button>
        </template>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { urlArchivo } from '@/core/chat/api'
import type { Archivo } from '@/core/chat/types'

const props = defineProps<{ fotos: Archivo[]; inicio: number }>()
const emit = defineEmits<{ cerrar: [] }>()

const indice = ref(0)
const raiz = ref<HTMLElement | null>(null)
const actual = computed(() => props.fotos[indice.value])

watch(
  () => props.fotos,
  (f) => {
    indice.value = Math.min(props.inicio, Math.max(0, f.length - 1))
    if (f.length) void nextTick(() => raiz.value?.focus({ preventScroll: true }))
  },
)
const mover = (d: number) => (indice.value = (indice.value + d + props.fotos.length) % props.fotos.length)
function tecla(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('cerrar')
  else if (e.key === 'ArrowLeft') mover(-1)
  else if (e.key === 'ArrowRight') mover(1)
}
</script>

<style scoped>
.visor {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: grid;
  place-items: center;
  background: rgb(10 12 16 / 0.92);
  outline: none;
}
.visor__barra {
  position: absolute;
  inset: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.75rem 1rem;
  font-size: 0.85rem;
  color: rgb(255 255 255 / 0.85);
}
.visor__imagen {
  max-width: calc(100vw - 2rem);
  max-height: calc(100dvh - 7rem);
  border-radius: 0.5rem;
  object-fit: contain;
  box-shadow: 0 20px 60px rgb(0 0 0 / 0.5);
}
.visor__boton {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 50%;
  color: white;
  background: rgb(255 255 255 / 0.08);
  cursor: pointer;
  &:hover {
    background: rgb(255 255 255 / 0.18);
  }
}
.visor__nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
}
.visor__nav--izq {
  left: 1rem;
}
.visor__nav--der {
  right: 1rem;
}
</style>
