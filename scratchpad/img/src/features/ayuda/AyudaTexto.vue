<template>
  <span>
    <template v-for="(trozo, i) in trozos" :key="i">
      <RouterLink
        v-if="trozo.guia && visibles.has(trozo.guia)"
        :to="{ name: 'ayuda', params: { guia: trozo.guia } }"
        class="ayuda-ref"
        >{{ trozo.texto }}</RouterLink
      >
      <template v-else>{{ trozo.texto }}</template>
    </template>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { partirReferencias } from '@/core/ayuda/reglas'

/**
 * Un párrafo del manual con sus referencias a otras guías. Un enlace solo se
 * pinta si la guía destino es una de las que ve este usuario (`visibles`); las
 * reglas de `veSiVeOrigen` garantizan que, al ver la guía, ve también sus enlaces.
 */
const props = defineProps<{ texto: string; visibles: ReadonlySet<string> }>()

const trozos = computed(() => partirReferencias(props.texto))
</script>

<style scoped>
.ayuda-ref {
  color: var(--p-primary-color);
  text-decoration: underline;
  text-underline-offset: 2px;
}
</style>
