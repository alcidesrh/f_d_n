<!--
  Un registro compartido en el chat: cabecera (id en la base de datos y abrir;
  el tipo y su ícono ya los dice el grupo de `TarjetasMensaje`) y el cuerpo
  propio del tipo, o la etiqueta si no tiene. Si quien lee no tiene permiso o
  el registro ya no existe, lo dice en lugar de mostrar datos.
-->
<template>
  <article class="tarjeta" :class="{ 'tarjeta--apagada': adjunto.estado !== 'ok' }">
    <header class="tarjeta__cabeza">
      <span class="min-w-0 flex-1">
        <span class="tarjeta__tipo">ID {{ adjunto.id }}</span>
        <span v-if="adjunto.estado === 'ok' && !tipo.componente" class="tarjeta__titulo">{{ adjunto.datos?.titulo }}</span>
      </span>
      <router-link v-if="adjunto.estado === 'ok'" :to="tipo.destino(adjunto)" class="tarjeta__abrir tap-target" :aria-label="`Abrir ${tipo.nombre} ${adjunto.id}`" v-tooltip.top="'Abrir'">
        <icon name="open-in-new" size="1.05rem" color="text-current" />
      </router-link>
    </header>
    <p v-if="adjunto.estado === 'sin_acceso'" class="tarjeta__aviso"><icon name="lock-outline" size=".9rem" color="text-current" /> No tiene permiso para ver este registro.</p>
    <p v-else-if="adjunto.estado === 'no_existe'" class="tarjeta__aviso"><icon name="block" size=".9rem" color="text-current" /> El registro ya no existe.</p>
    <component :is="tipo.componente" v-else-if="tipo.componente" :datos="adjunto.datos" :id="adjunto.id" />
  </article>
</template>

<script setup lang="ts">
import type { Adjunto } from '@/core/chat/types'
import { tipoTarjeta } from './catalogo'

const props = defineProps<{ adjunto: Adjunto }>()
const tipo = computed(() => tipoTarjeta(props.adjunto.tipo))
</script>

<style scoped>
.tarjeta {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  padding: 0.75rem 0.85rem;
  border-radius: 0.75rem;
  background: var(--p-content-background);
  border: 1px solid var(--p-surface-200);
  color: var(--p-surface-700);
  text-align: left;
  min-width: 0;
}
.tarjeta--apagada {
  background: transparent;
}
.tarjeta__cabeza {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
}
.tarjeta__tipo {
  display: block;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--p-surface-500);
}
.tarjeta__titulo {
  display: block;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.tarjeta__abrir {
  display: grid;
  place-items: center;
  color: var(--p-surface-500);
  border-radius: 0.5rem;
  transition: color var(--transition), background var(--transition);
  &:hover {
    color: var(--p-primary-color);
    background: var(--p-surface-100);
  }
}
.tarjeta__aviso {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  font-size: 0.8rem;
  color: var(--p-surface-500);
}
</style>
