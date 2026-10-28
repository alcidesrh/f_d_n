<template>
  <div class="nf">
    <div class="nf-fondo" aria-hidden="true" />

    <header class="nf-top">
      <RouterLink to="/" class="nf-brand" aria-label="Ir al inicio">
        <span class="nf-mark"><icon name="directions-bus" color="text-inherit" /></span>
        <span class="nf-name">F D N</span>
      </RouterLink>
    </header>

    <main class="nf-content">
      <div class="nf-escena" aria-hidden="true">
        <span class="nf-code">404</span>
        <svg class="nf-mapa" viewBox="0 0 600 170" fill="none">
          <path id="nf-trayecto" class="nf-via" d="M40 120 C 140 120, 170 50, 290 66 S 440 136, 520 92" />
          <path class="nf-via-flujo" d="M40 120 C 140 120, 170 50, 290 66 S 440 136, 520 92" />
          <path class="nf-via-perdida" d="M520 92 Q 545 78 566 62" />

          <circle class="nf-parada" cx="40" cy="120" r="7" />
          <circle class="nf-parada-centro" cx="40" cy="120" r="2.5" />
          <circle class="nf-intermedia" cx="290" cy="66" r="4.5" />

          <g class="nf-destino" transform="translate(572 54)">
            <circle class="nf-pulso" r="16" />
            <circle class="nf-pin" r="13" />
            <text class="nf-pin-texto" y="5" text-anchor="middle">?</text>
          </g>

          <g :transform="quieto ? 'translate(290 66)' : undefined">
            <g class="nf-bus" transform="scale(1.35) translate(-19 -24)">
              <rect class="nf-bus-cuerpo" width="38" height="18" rx="4" />
              <rect class="nf-bus-ventana" x="4" y="4" width="22" height="6" rx="1.5" />
              <rect class="nf-bus-ventana" x="29" y="4" width="6" height="8" rx="1.5" />
              <circle class="nf-bus-rueda" cx="9" cy="19" r="3.5" />
              <circle class="nf-bus-rueda" cx="29" cy="19" r="3.5" />
            </g>
            <animateMotion v-if="!quieto" dur="7s" repeatCount="indefinite" keyPoints="0;1;1" keyTimes="0;0.82;1" calcMode="spline" keySplines="0.45 0 0.25 1;0 0 1 1" rotate="auto">
              <mpath href="#nf-trayecto" />
            </animateMotion>
          </g>
        </svg>
      </div>

      <p class="nf-eyebrow"><span class="nf-dot" />Error 404 · Ruta no encontrada</p>
      <h1>Esta ruta no está en el mapa</h1>
      <p class="nf-text">La página que buscas no existe o cambió de lugar. Revisa la dirección o vuelve a un destino conocido.</p>
      <p class="nf-path" :title="route.fullPath"><icon name="link" size="1rem" color="text-inherit" /><code>{{ route.fullPath }}</code></p>

      <div class="nf-actions">
        <Button label="Volver al inicio" icon="pi pi-home" class="nf-btn" @click="router.push('/')" />
        <Button label="Regresar" icon="pi pi-arrow-left" severity="secondary" outlined class="nf-btn" @click="router.back()" />
      </div>
    </main>

    <footer class="nf-legal">Transportes Fuentes del Norte</footer>
  </div>
</template>

<script setup lang="ts">
const router = useRouter();
const route = useRoute();
/** Sin animación del bus si el sistema pide reducir el movimiento: queda detenido a mitad del trayecto. */
const quieto = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
</script>

<style scoped>
.nf {
  position: relative;
  isolation: isolate;
  display: grid;
  grid-template-rows: auto 1fr auto;
  min-height: 100dvh;
  padding: 1.25rem 1rem;
  overflow: hidden;
  background: var(--p-surface-0);
  @media (min-width: 48rem) {
    padding: 1.75rem 2.5rem;
  }
}
/* Retícula de puntos con un halo del color primario. */
.nf-fondo {
  position: absolute;
  inset: 0;
  z-index: -1;
  background:
    radial-gradient(45% 40% at 50% 30%, color-mix(in srgb, var(--p-primary-color) 14%, transparent), transparent 70%),
    radial-gradient(circle at 1px 1px, var(--p-surface-300) 1px, transparent 0) 0 0 / 22px 22px;
  mask-image: radial-gradient(70% 60% at 50% 35%, #000 30%, transparent 85%);
  pointer-events: none;
}

.nf-top {
  display: flex;
}
.nf-brand {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  color: var(--p-surface-900);
  text-decoration: none;
}
.nf-mark {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.7rem;
  font-size: 1.25rem;
  color: var(--p-primary-contrast-color);
  background: linear-gradient(135deg, var(--p-primary-400), var(--p-primary-600));
  box-shadow: 0 8px 18px -8px var(--p-primary-color);
}
.nf-name {
  font-family: "Faster One";
  font-size: 1.6rem;
  line-height: 1;
}

.nf-content {
  align-self: center;
  justify-self: center;
  width: min(40rem, 100%);
  padding-block: 2rem;
  text-align: center;
  animation: subir 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
}

/* ---------- Escena: 404 con el trayecto encima ---------- */
.nf-escena {
  position: relative;
  display: grid;
  place-items: center;
  margin-bottom: 1.25rem;
}
.nf-code {
  grid-area: 1 / 1;
  font-size: clamp(7rem, 30vw, 13rem);
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.06em;
  color: transparent;
  background: linear-gradient(180deg, color-mix(in srgb, var(--p-primary-color) 32%, transparent), color-mix(in srgb, var(--p-primary-color) 4%, transparent) 90%);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-stroke: 1.5px color-mix(in srgb, var(--p-primary-color) 45%, transparent);
  user-select: none;
}
.nf-mapa {
  grid-area: 1 / 1;
  width: 100%;
  height: auto;
  overflow: visible;
}
.nf-via {
  stroke: color-mix(in srgb, var(--p-surface-400) 35%, transparent);
  stroke-width: 12;
  stroke-linecap: round;
}
.nf-via-flujo {
  stroke: var(--p-primary-color);
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-dasharray: 2 12;
  animation: fluir 1.4s linear infinite;
}
.nf-via-perdida {
  stroke: var(--p-surface-300);
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-dasharray: 3 7;
}
.nf-parada {
  fill: var(--p-surface-0);
  stroke: var(--p-primary-color);
  stroke-width: 3;
}
.nf-parada-centro {
  fill: var(--p-primary-color);
}
.nf-intermedia {
  fill: var(--p-surface-0);
  stroke: var(--p-surface-400);
  stroke-width: 2.5;
}
.nf-pin {
  fill: var(--c-warning);
}
.nf-pin-texto {
  fill: var(--p-surface-0);
  font-size: 15px;
  font-weight: 800;
}
.nf-pulso {
  fill: var(--c-warning);
  opacity: 0.35;
  transform-box: fill-box;
  transform-origin: center;
  animation: pulso 2s ease-out infinite;
}
.nf-bus-cuerpo {
  fill: var(--p-primary-color);
}
.nf-bus-ventana {
  fill: var(--p-primary-contrast-color);
  opacity: 0.85;
}
.nf-bus-rueda {
  fill: var(--p-surface-800);
  stroke: var(--p-surface-0);
  stroke-width: 1.5;
}

/* ---------- Texto y acciones ---------- */
.nf-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 1rem;
  padding: 0.3rem 0.8rem 0.3rem 0.65rem;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--c-warning-ink);
  background: var(--c-warning-soft);
}
.nf-dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 50%;
  background: var(--c-warning);
}
h1 {
  margin: 0;
  font-size: clamp(1.7rem, 4.5vw, 2.5rem);
  font-weight: 700;
  line-height: 1.15;
  letter-spacing: -0.025em;
  text-wrap: balance;
  color: var(--p-surface-900);
}
.nf-text {
  margin: 0.85rem auto 0;
  max-width: 28rem;
  font-size: 0.98rem;
  line-height: 1.6;
  color: var(--p-surface-500);
}
.nf-path {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  max-width: 100%;
  margin: 1.25rem 0 0;
  padding: 0.4rem 0.75rem;
  border-radius: 0.6rem;
  font-size: 0.8rem;
  color: var(--p-surface-500);
  background: var(--p-surface-50);
  border: 1px solid var(--p-surface-200);
  code {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    color: var(--p-surface-700);
  }
}
.nf-actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 2rem;
  @media (min-width: 48rem) {
    flex-direction: row;
    justify-content: center;
  }
}
.nf-btn {
  padding: 0.7rem 1.4rem;
  font-weight: 600;
  border-radius: 0.75rem;
}

.nf-legal {
  font-size: 0.72rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  text-align: center;
  color: var(--p-surface-400);
}

@keyframes subir {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
}
@keyframes fluir {
  to {
    stroke-dashoffset: -28;
  }
}
@keyframes pulso {
  from {
    transform: scale(0.8);
    opacity: 0.45;
  }
  to {
    transform: scale(1.8);
    opacity: 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .nf-content,
  .nf-via-flujo,
  .nf-pulso {
    animation: none;
  }
}
</style>
