<!--
  Videos cortos sobre lo nuevo del sistema (`public/videos/`), en orden. Se
  reproducen al hacer clic; al empezar uno se pausa el que estuviera sonando.
-->
<template>
  <section class="videos" aria-label="Videos sobre el nuevo sistema">
    <figure v-for="(v, i) in VIDEOS" :key="v.archivo" class="video">
      <video
        :ref="(el) => (elementos[i] = el as HTMLVideoElement | null)"
        :src="`${base}videos/${v.archivo}#t=0.5`"
        controls
        preload="metadata"
        playsinline
        @play="pausarOtros(i)"
      />
      <figcaption><span class="numero">{{ i + 1 }}</span>{{ v.titulo }}</figcaption>
    </figure>
  </section>
</template>

<script setup lang="ts">
const VIDEOS = [
  { archivo: "introduccion.mp4", titulo: "Introducción" },
  { archivo: "boleto.mp4", titulo: "Boleto" },
  { archivo: "intermedias.mp4", titulo: "Intermedias" },
  { archivo: "reasignar&anular.mp4", titulo: "Reasignar y anular" },
  { archivo: "chat&correo.mp4", titulo: "Chat y correo" },
];
const base = import.meta.env.BASE_URL;
const elementos: (HTMLVideoElement | null)[] = [];

function pausarOtros(actual: number) {
  elementos.forEach((el, i) => i !== actual && el && !el.paused && el.pause());
}
</script>

<style scoped>
.videos {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 1rem;
}
.video {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem;
  border-radius: var(--p-content-border-radius, 0.75rem);
  border: 1px solid var(--p-content-border-color);
  background: var(--p-content-background);
}
video {
  width: 100%;
  aspect-ratio: 16 / 9;
  border-radius: calc(var(--p-content-border-radius, 0.75rem) - 0.25rem);
  background: #000;
}
figcaption {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
}
.numero {
  display: inline-grid;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  font-size: 0.8rem;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
@container (min-width: 40rem) {
  .videos {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@container (min-width: 64rem) {
  .videos {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
