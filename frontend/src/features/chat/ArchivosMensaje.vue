<!--
  Fotos y documentos de un mensaje. Fotos en mosaico (una sola, grande, con su
  proporción para no saltar al cargar); documentos como fila con descarga.
-->
<template>
  <div class="archivos">
    <div v-if="fotos.length" class="fotos" :class="`fotos--${Math.min(fotos.length, 4)}`">
      <button v-for="(f, i) in fotos.slice(0, 4)" :key="f.id" type="button" class="foto" :style="fotos.length === 1 ? proporcion(f) : undefined" :aria-label="`Ver ${f.nombre}`" @click="emit('ver', fotos, i)">
        <img :src="urlArchivo(f)" :alt="f.nombre" loading="lazy" />
        <span v-if="i === 3 && fotos.length > 4" class="foto__mas">+{{ fotos.length - 4 }}</span>
      </button>
    </div>
    <a v-for="d in documentos" :key="d.id" :href="urlArchivo(d, true)" class="doc" :download="d.nombre">
      <span class="doc__icono"><icon :name="icono(d.tipo)" color="text-current" /></span>
      <span class="min-w-0 flex-1">
        <span class="doc__nombre">{{ d.nombre }}</span>
        <span class="doc__tamano">{{ tamanoLegible(d.tamano) }}</span>
      </span>
      <icon name="download" size="1.1rem" color="text-current" />
    </a>
  </div>
</template>

<script setup lang="ts">
import { urlArchivo } from '@/core/chat/api'
import { tamanoLegible } from '@/core/chat/modelo'
import type { Archivo } from '@/core/chat/types'

const props = defineProps<{ archivos: Archivo[] }>()
const emit = defineEmits<{ ver: [fotos: Archivo[], indice: number] }>()

const fotos = computed(() => props.archivos.filter((a) => a.imagen))
const documentos = computed(() => props.archivos.filter((a) => !a.imagen))

/** Una sola foto: caja con su proporción (entre 3:4 y 16:9) para reservar el alto. */
function proporcion(f: Archivo) {
  if (!f.ancho || !f.alto) return undefined
  return { aspectRatio: String(Math.min(16 / 9, Math.max(3 / 4, f.ancho / f.alto))) }
}
const icono = (tipo: string) => (tipo === 'application/pdf' ? 'picture-as-pdf-outline' : tipo.includes('sheet') || tipo === 'text/csv' ? 'table-chart-outline' : 'description-outline')
</script>

<style scoped>
.archivos {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  width: min(20rem, 70cqi);
  max-width: 100%;
}
.fotos {
  display: grid;
  gap: 2px;
  overflow: hidden;
  border-radius: 0.8rem;
}
.fotos--2,
.fotos--4 {
  grid-template-columns: 1fr 1fr;
}
.fotos--3 {
  grid-template-columns: 2fr 1fr;
  grid-template-rows: 1fr 1fr;
  .foto:first-child {
    grid-row: span 2;
  }
}
.foto {
  position: relative;
  display: block;
  min-height: 6rem;
  overflow: hidden;
  background: var(--p-surface-200);
  cursor: zoom-in;
  img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.25s var(--ease);
  }
  &:hover img {
    transform: scale(1.03);
  }
}
.fotos--1 .foto {
  min-height: 8rem;
  max-height: 22rem;
}
.fotos--2 .foto,
.fotos--4 .foto {
  aspect-ratio: 1;
}
.foto__mas {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-size: 1.4rem;
  font-weight: 600;
  color: white;
  background: rgb(0 0 0 / 0.45);
}
.doc {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.55rem 0.7rem;
  border-radius: 0.7rem;
  text-decoration: none;
  color: var(--p-surface-700);
  background: var(--p-content-background);
  border: 1px solid var(--p-surface-200);
  &:hover {
    border-color: var(--p-primary-color);
  }
}
.doc__icono {
  display: grid;
  place-items: center;
  flex: none;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 0.5rem;
  color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
}
.doc__nombre,
.doc__tamano {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.doc__nombre {
  font-size: 0.85rem;
  font-weight: 500;
}
.doc__tamano {
  font-size: 0.72rem;
  color: var(--p-surface-500);
}
</style>
