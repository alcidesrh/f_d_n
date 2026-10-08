<!--
  Avatar del chat: iniciales sobre un tono estable por id (persona o grupo),
  campana para los avisos del sistema y, opcionalmente, una marca del ámbito
  (estación / agencia). Con `foto` (URL) muestra la foto de perfil en su lugar;
  si la imagen no carga, vuelve a las iniciales.
-->
<template>
  <span class="chat-avatar" :class="[`chat-avatar--${tamano}`, { 'chat-avatar--sistema': sistema }]" :style="{ '--tono': tono(id) * 45 }" :aria-hidden="true">
    <img v-if="src && !fallo" :src="src" alt="" class="chat-avatar__foto" @error="fallo = true" />
    <icon v-else-if="sistema" name="notifications-outline" color="text-current" />
    <icon v-else-if="grupo" name="group-outline" color="text-current" />
    <template v-else>{{ iniciales(nombre) }}</template>
    <span v-if="ambito && ambito !== 'administracion'" class="chat-avatar__ambito" :title="ambito === 'agencia' ? 'Agencia' : 'Estación'">
      <icon :name="ambito === 'agencia' ? 'handshake-outline' : 'storefront-outline'" size=".65rem" color="text-current" />
    </span>
  </span>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { urlFoto } from '@/core/cuenta/api'
import { iniciales, tono } from '@/core/chat/modelo'
import type { Ambito } from '@/core/chat/types'

const props = withDefaults(defineProps<{ id: number; nombre: string; ambito?: Ambito | null; grupo?: boolean; sistema?: boolean; foto?: string | null; tamano?: 'sm' | 'md' | 'lg' | 'xl' }>(), { ambito: null, grupo: false, sistema: false, foto: null, tamano: 'md' })

/** `foto` llega como URL absoluta (la propia) o como ruta firmada de la API (la de otros). */
const src = computed(() => (props.foto?.startsWith('/') ? urlFoto(props.foto) : props.foto))
const fallo = ref(false)
watch(() => props.foto, () => (fallo.value = false))
</script>

<style scoped>
.chat-avatar {
  --d: 2.5rem;
  position: relative;
  flex: none;
  display: inline-grid;
  place-items: center;
  width: var(--d);
  height: var(--d);
  border-radius: 50%;
  font-size: calc(var(--d) * 0.38);
  font-weight: 600;
  letter-spacing: 0.02em;
  color: hsl(calc(var(--tono) * 1deg) 55% 32%);
  background: hsl(calc(var(--tono) * 1deg) 70% 90%);
  user-select: none;
}
.chat-avatar--sistema {
  color: var(--p-primary-contrast-color);
  background: var(--p-primary-color);
}
.chat-avatar--sm {
  --d: 1.75rem;
}
.chat-avatar--lg {
  --d: 3rem;
}
.chat-avatar--xl {
  --d: 7rem;
}
.chat-avatar__foto {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
}
.chat-avatar__ambito {
  position: absolute;
  right: -2px;
  bottom: -2px;
  display: grid;
  place-items: center;
  width: 1rem;
  height: 1rem;
  border-radius: 50%;
  color: var(--p-surface-600);
  background: var(--p-content-background);
  box-shadow: 0 0 0 1.5px var(--p-content-background);
}
</style>
