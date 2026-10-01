<!--
  Cambia el idioma quedándose en la misma página (cambia el prefijo de la
  URL). `lista`: todos visibles (menú móvil); si no, un desplegable.
-->
<template>
  <nav v-if="lista" :aria-label="t('nav.idioma')" class="flex flex-wrap gap-2">
    <RouterLink
      v-for="i in IDIOMAS"
      :key="i"
      :to="enIdioma(i)"
      :hreflang="i"
      :lang="i"
      class="rounded-full border px-3 py-1.5 text-sm no-underline"
      :class="i === actual ? 'border-primary bg-primary text-primary-contrast' : 'border-surface-300 text-color'"
      @click="emit('elegido')"
    >
      {{ NOMBRES_IDIOMA[i] }}
    </RouterLink>
  </nav>
  <div v-else ref="raiz" class="relative">
    <button
      type="button"
      class="flex h-10 items-center gap-1.5 rounded-full px-3 text-sm font-medium text-color hover:bg-surface-100"
      :aria-label="t('nav.idioma')"
      :aria-expanded="abierto"
      aria-haspopup="menu"
      @click="abierto = !abierto"
    >
      <icon name="world" size="1.15rem" />
      <span class="uppercase">{{ actual }}</span>
      <icon name="chevron-down" size="0.9rem" />
    </button>
    <ul
      v-if="abierto"
      role="menu"
      class="absolute right-0 top-11 z-30 m-0 min-w-40 list-none rounded-xl border border-surface-200 bg-white p-1 shadow-lg"
    >
      <li v-for="i in IDIOMAS" :key="i" role="none">
        <RouterLink
          role="menuitem"
          :to="enIdioma(i)"
          :hreflang="i"
          :lang="i"
          class="flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm text-color no-underline hover:bg-surface-100"
          @click="abierto = false"
        >
          {{ NOMBRES_IDIOMA[i] }}
          <icon v-if="i === actual" name="check" size="1rem" class="text-primary" />
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { IDIOMAS, NOMBRES_IDIOMA, type Idioma } from '@/i18n'

defineProps<{ lista?: boolean }>()
const emit = defineEmits<{ elegido: [] }>()
const { t, locale } = useI18n()
const route = useRoute()
const abierto = ref(false)
const raiz = ref<HTMLElement | null>(null)
const actual = computed(() => locale.value as Idioma)

const enIdioma = (idioma: Idioma) => ({ name: route.name ?? 'inicio', params: { ...route.params, idioma }, query: route.query, hash: route.hash })

function fuera(e: MouseEvent) {
  if (abierto.value && raiz.value && !raiz.value.contains(e.target as Node)) abierto.value = false
}
onMounted(() => document.addEventListener('click', fuera))
onBeforeUnmount(() => document.removeEventListener('click', fuera))
</script>
