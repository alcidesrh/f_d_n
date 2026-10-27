<template>
  <div class="nf">
    <div class="nf-glow" aria-hidden="true" />
    <main class="nf-content">
      <div class="nf-code" aria-hidden="true">
        <span>4</span>
        <span class="nf-bus"><icon name="directions-bus" /></span>
        <span>4</span>
      </div>

      <p class="nf-eyebrow">Error 404</p>
      <h1>Esta ruta no está en el mapa</h1>
      <p class="nf-text">La página que buscas no existe o fue movida a otra ubicación dentro del sistema. Revisa la dirección o vuelve a un destino conocido.</p>

      <div class="nf-actions">
        <Button label="Volver al inicio" icon="pi pi-home" @click="router.push('/')" />
        <Button label="Regresar" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.back()" />
      </div>

      <div class="nf-road" aria-hidden="true" />
    </main>
  </div>
</template>

<script setup lang="ts">
const router = useRouter();
</script>

<style scoped>
.nf {
  position: relative;
  display: grid;
  place-items: center;
  min-height: 100dvh;
  padding: 1.5rem;
  overflow: hidden;
  text-align: center;
  background: var(--p-surface-0);
}
.nf-glow {
  position: absolute;
  inset: -20% 0 auto;
  height: 70%;
  background:
    radial-gradient(40% 60% at 30% 40%, color-mix(in srgb, var(--p-primary-color) 22%, transparent), transparent),
    radial-gradient(35% 50% at 72% 55%, color-mix(in srgb, var(--p-primary-400) 16%, transparent), transparent);
  filter: blur(30px);
  pointer-events: none;
}
.nf-content {
  position: relative;
  max-width: 34rem;
  animation: entrada 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.nf-code {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  font-size: clamp(6rem, 22vw, 10rem);
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.04em;
  background: linear-gradient(180deg, var(--p-primary-400), var(--p-primary-700));
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  user-select: none;
}
.nf-bus {
  display: inline-grid;
  place-items: center;
  --lado: clamp(4rem, 15vw, 7rem);
  width: var(--lado);
  height: var(--lado);
  font-size: calc(var(--lado) * 0.55);
  border-radius: 28%;
  color: var(--p-primary-contrast-color);
  background: linear-gradient(135deg, var(--p-primary-400), var(--p-primary-600));
  box-shadow: 0 18px 40px -14px var(--p-primary-color);
  animation: flotar 4s ease-in-out infinite;
}
.nf-eyebrow {
  margin: 1.5rem 0 0.25rem;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--p-primary-color);
}
h1 {
  margin: 0;
  font-size: clamp(1.6rem, 4vw, 2.2rem);
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--p-surface-900);
}
.nf-text {
  margin: 0.75rem auto 0;
  max-width: 28rem;
  font-size: 0.95rem;
  line-height: 1.6;
  color: var(--p-surface-500);
}
.nf-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.75rem;
  margin-top: 2rem;
}
.nf-road {
  margin: 3rem auto 0;
  width: min(100%, 22rem);
  height: 2px;
  background: repeating-linear-gradient(90deg, var(--p-surface-400) 0 14px, transparent 14px 26px);
  opacity: 0.6;
  mask-image: linear-gradient(90deg, transparent, #000 25%, #000 75%, transparent);
}
@keyframes entrada {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
}
@keyframes flotar {
  50% {
    transform: translateY(-8px) rotate(-3deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .nf-content,
  .nf-bus {
    animation: none;
  }
}
</style>
