<!--
  Bandera del idioma (SVG propio: los emojis de bandera no se ven en Windows).
  En = Reino Unido; Es = España.
-->
<template>
  <svg viewBox="0 0 24 16" class="inline-block shrink-0 rounded-[2px] shadow-[0_0_0_1px_rgb(0_0_0/0.15)]" :style="{ width: size, height: `calc(${size} * 2 / 3)` }" aria-hidden="true">
    <template v-if="idioma === 'es'">
      <rect width="24" height="16" fill="#c60b1e" />
      <rect y="4" width="24" height="8" fill="#ffc400" />
    </template>
    <template v-else-if="idioma === 'en'">
      <rect width="24" height="16" fill="#012169" />
      <path d="M0 0l24 16M24 0L0 16" stroke="#fff" stroke-width="3.2" />
      <path d="M0 0l24 16M24 0L0 16" stroke="#c8102e" stroke-width="1.2" />
      <path d="M12 0v16M0 8h24" stroke="#fff" stroke-width="5.2" />
      <path d="M12 0v16M0 8h24" stroke="#c8102e" stroke-width="3" />
    </template>
    <template v-else-if="idioma === 'fr'">
      <rect width="24" height="16" fill="#fff" />
      <rect width="8" height="16" fill="#0055a4" />
      <rect x="16" width="8" height="16" fill="#ef4135" />
    </template>
    <template v-else-if="idioma === 'de'">
      <rect width="24" height="16" fill="#ffce00" />
      <rect width="24" height="5.34" fill="#000" />
      <rect y="5.33" width="24" height="5.34" fill="#dd0000" />
    </template>
    <template v-else>
      <rect width="24" height="16" fill="#fff" />
      <rect width="8" height="16" fill="#009246" />
      <rect x="16" width="8" height="16" fill="#ce2b37" />
    </template>
  </svg>
</template>

<script setup lang="ts">
import type { Idioma } from '@/i18n'

withDefaults(defineProps<{ idioma: Idioma; size?: string }>(), { size: '1.25rem' })
</script>
