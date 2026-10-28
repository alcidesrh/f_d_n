<template>
  <div class="lp">
    <!-- Panel visual: banda de cabecera en el teléfono, mitad izquierda desde `lg`. -->
    <aside class="lp-visual" aria-hidden="true">
      <div v-for="(foto, i) in FOTOS" :key="foto" class="lp-foto" :class="{ activa: i === actual }" :style="{ backgroundImage: `url('images/login${foto}.png')` }" />
      <div class="lp-scrim" />

      <div class="lp-visual-inner">
        <div class="lp-brand">
          <span class="lp-mark"><icon name="directions-bus" color="text-inherit" /></span>
          <span class="lp-brand-text">
            <span class="lp-name">F D N</span>
            <span class="lp-sub">Transportes Fuentes del Norte</span>
          </span>
        </div>

        <div class="lp-story">
          <p class="lp-eyebrow">Plataforma de operación</p>
          <h2 class="lp-headline">Cada salida, cada asiento,<br />cada encomienda.</h2>
          <p class="lp-lead">Venta en taquilla y en línea, salidas, encomiendas y seguimiento de la flota, en un mismo lugar para todas las empresas del grupo.</p>

          <div class="lp-progreso">
            <span v-for="(foto, i) in FOTOS" :key="foto" class="lp-barra" :class="{ activa: i === actual }" />
          </div>
        </div>

        <ul class="lp-logos">
          <li v-for="c in COMPANIES" :key="c.name"><img :src="c.logo" :alt="c.name" /></li>
        </ul>
      </div>
    </aside>

    <main class="lp-panel">
      <div id="login" ref="card" class="lp-form" :class="{ 'is-loading': loading }">
        <header class="lp-head">
          <p class="lp-kicker"><span class="lp-dot" />Acceso al sistema</p>
          <h1>Bienvenido de nuevo</h1>
          <p>Ingresa con tu usuario y contraseña para continuar.</p>
        </header>

        <FormKit type="form" :actions="false" @submit-invalid="shake" @submit="handleSubmit">
          <div class="grid gap-5">
            <FormKit type="InputText" name="username" label="Usuario" validation="required" autocapitalize="none" autocomplete="username" autocorrect="off" spellcheck="false" :style="{ textTransform: 'none' }" placeholder="Tu usuario" fluid />
            <FormKit type="Password" name="password" label="Contraseña" validation="required" placeholder="Tu contraseña" fluid />
          </div>
          <div v-if="error" class="lp-error" role="alert">
            <icon name="error-outline" size="1.15rem" color="text-red-600" />
            <FormKitMessages />
          </div>
          <Button type="submit" label="Iniciar sesión" icon="pi pi-arrow-right" icon-pos="right" :loading="loading" class="lp-submit" />
        </FormKit>

        <footer class="lp-companies">
          <div class="lp-companies-title"><span>Empresas del grupo</span></div>
          <ul>
            <li v-for="c in COMPANIES" :key="c.name"><img :src="c.logo" :alt="c.name" class="dark:opacity-75 dark:brightness-0 dark:invert" /></li>
          </ul>
        </footer>
      </div>

      <p class="lp-legal">
        <icon name="lock-outline" size=".95rem" color="text-inherit" />
        Conexión segura · © {{ YEAR }} Transportes Fuentes del Norte
      </p>
    </main>
  </div>
</template>

<script lang="ts" setup>
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from "vue";
import type { FormKitNode } from "@formkit/core";
import { FormKitMessages } from "@formkit/vue";
import { gsap } from "gsap";
import { CustomEase } from "gsap/CustomEase";
import { CustomWiggle } from "gsap/CustomWiggle";
import { useRoute } from "vue-router";
import { router } from "@/app/router";
import { syncVueRoutes } from "@/app/routeSync";
import { destinoSeguro, REDIRECT_QUERY } from "@/core/auth/redirect";
import { useSessionStore } from "@/core/auth/session";
import { HttpError } from "@/core/http";
import { useLoadingStore } from "@/core/loading";
import { useMetadataStore } from "@/core/metadata/sincronizacion";

gsap.registerPlugin(CustomEase, CustomWiggle);

/** Empresas que operan el servicio. */
const COMPANIES = [
  { name: "La Pionera", logo: "images/logos/copiloto/lapionera5.png" },
  { name: "Rosita", logo: "images/logos/copiloto/rosita5.png" },
  { name: "Maya de Oro", logo: "images/logos/copiloto/mayadeoro5.png" },
  { name: "Starbus", logo: "images/logos/copiloto/starbus5.png" },
  { name: "Corporación La Pionera", logo: "images/logos/copiloto/corporacionlapionera5.png" },
];

/** Fotos del panel visual (`images/loginN.png`): las de carretera, sin texto encima. */
const FOTOS = [4, 7, 8, 9, 10];
/** Tiempo de cada foto; igual a la duración de `.lp-barra.activa`. */
const FOTO_MS = 7000;
const YEAR = new Date().getFullYear();

const route = useRoute();
const card = useTemplateRef<HTMLElement>("card");
const session = useSessionStore();
const loadingStore = useLoadingStore();
const loading = computed(() => loadingStore.isLoading("login"));
const error = ref(false);
const actual = ref(Math.floor(Math.random() * FOTOS.length));

let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
  FOTOS.forEach((n) => (new Image().src = `images/login${n}.png`));
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  timer = setInterval(() => (actual.value = (actual.value + 1) % FOTOS.length), FOTO_MS);
});
onBeforeUnmount(() => clearInterval(timer));

/** Sacude el formulario ante datos inválidos o credenciales rechazadas. */
function shake() {
  gsap.to(card.value, {
    x: -18,
    duration: 1.2,
    ease: CustomWiggle.create("loginWiggle", { wiggles: 8, type: "easeInOut" }),
  });
}

async function handleSubmit(credentials: { username: string; password: string }, node: FormKitNode) {
  error.value = false;
  node.clearErrors();
  try {
    await session.login(credentials);
    // El arranque no tenía sesión: sin manifiesto (y sin schema si no estaba guardado, 401). Ya autenticado se repite.
    await useMetadataStore().arrancar();
    void syncVueRoutes();
    await router.push(destinoSeguro(route.query[REDIRECT_QUERY]) ?? { name: "dashboard" });
  } catch (cause) {
    error.value = true;
    const invalid = cause instanceof HttpError && cause.status === 401;
    node.setErrors([invalid ? "Usuario o contraseña incorrecto." : cause instanceof Error ? cause.message : String(cause)]);
    shake();
  }
}
</script>

<style scoped>
.lp {
  display: grid;
  min-height: 100dvh;
  background: var(--p-surface-0);
  @media (min-width: 64rem) {
    grid-template-columns: minmax(0, 1.15fr) minmax(28rem, 1fr);
  }
}

/* ---------- Panel visual ----------
   Siempre sobre foto oscurecida: el texto es claro en ambos modos. */
.lp-visual {
  --foto-tinta: rgb(255 255 255);
  position: relative;
  isolation: isolate;
  min-height: 14rem;
  overflow: hidden;
  color: var(--foto-tinta);
  background: var(--p-surface-950);
  @media (min-width: 64rem) {
    min-height: 100dvh;
    position: sticky;
    top: 0;
  }
}
.lp-foto {
  position: absolute;
  inset: 0;
  z-index: -2;
  background-size: cover;
  background-position: center;
  opacity: 0;
  transform: scale(1.08);
  transition:
    opacity 1.6s ease,
    transform 9s ease-out;
  &.activa {
    opacity: 1;
    transform: scale(1);
  }
}
.lp-scrim {
  position: absolute;
  inset: 0;
  z-index: -1;
  background:
    linear-gradient(180deg, rgb(6 10 20 / 0.55) 0%, rgb(6 10 20 / 0.1) 35%, rgb(6 10 20 / 0.9) 100%),
    linear-gradient(90deg, rgb(6 10 20 / 0.5) 0%, transparent 70%),
    radial-gradient(120% 80% at 0% 100%, color-mix(in srgb, var(--p-primary-500) 35%, transparent), transparent 60%);
}
.lp-visual-inner {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 2rem;
  height: 100%;
  padding: 1.5rem 1.25rem 2.75rem;
  @media (min-width: 64rem) {
    padding: 2.75rem 3.5rem;
  }
}

.lp-brand {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}
.lp-mark {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 0.85rem;
  font-size: 1.5rem;
  background: rgb(255 255 255 / 0.12);
  border: 1px solid rgb(255 255 255 / 0.22);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}
.lp-brand-text {
  display: grid;
  gap: 0.3rem;
}
.lp-name {
  font-family: "Faster One";
  font-size: 2.1rem;
  line-height: 0.9;
  white-space: nowrap;
}
.lp-sub {
  font-size: 0.62rem;
  font-weight: 600;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  opacity: 0.8;
}

.lp-story,
.lp-logos {
  display: none;
  @media (min-width: 64rem) {
    display: block;
  }
}
.lp-story {
  max-width: 32rem;
  margin-top: auto;
  animation: subir 0.9s 0.15s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.lp-eyebrow {
  margin: 0 0 0.9rem;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  opacity: 0.75;
}
.lp-headline {
  margin: 0;
  font-size: clamp(2rem, 3.2vw, 3rem);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -0.025em;
  text-wrap: balance;
}
.lp-lead {
  margin: 1.1rem 0 0;
  max-width: 28rem;
  font-size: 0.98rem;
  line-height: 1.65;
  opacity: 0.78;
}
.lp-progreso {
  display: flex;
  gap: 0.4rem;
  margin-top: 2rem;
}
.lp-barra {
  position: relative;
  width: 2.25rem;
  height: 3px;
  overflow: hidden;
  border-radius: 3px;
  background: rgb(255 255 255 / 0.25);
  &::after {
    content: "";
    position: absolute;
    inset: 0;
    background: var(--foto-tinta);
    transform: scaleX(0);
    transform-origin: left;
  }
  &.activa::after {
    animation: llenar 7s linear forwards;
  }
}
.lp-logos {
  margin: 0;
  padding: 1.5rem 0 0;
  list-style: none;
  border-top: 1px solid rgb(255 255 255 / 0.16);
  @media (min-width: 64rem) {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem 1.75rem;
  }
  img {
    height: 1.6rem;
    width: auto;
    filter: brightness(0) invert(1);
    opacity: 0.7;
    transition: opacity 0.2s;
    &:hover {
      opacity: 1;
    }
  }
}

/* ---------- Panel del formulario ---------- */
.lp-panel {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2rem;
  margin-top: -1.5rem;
  padding: 2.25rem 1rem 1.5rem;
  border-radius: 1.5rem 1.5rem 0 0;
  background:
    radial-gradient(60% 40% at 100% 0%, color-mix(in srgb, var(--p-primary-color) 7%, transparent), transparent),
    var(--p-surface-0);
  @media (min-width: 64rem) {
    justify-content: center;
    margin-top: 0;
    padding: 3rem 3.5rem 2rem;
    border-radius: 0;
  }
}
.lp-form {
  width: min(24rem, 100%);
  margin-block: auto;
  animation: subir 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
  transition: opacity 0.2s;
  &.is-loading {
    opacity: 0.6;
  }
}
.lp-head {
  margin-bottom: 2rem;
  h1 {
    margin: 0;
    font-size: clamp(1.6rem, 2.4vw, 2rem);
    font-weight: 700;
    letter-spacing: -0.025em;
    color: var(--p-surface-900);
  }
  p:last-child {
    margin: 0.45rem 0 0;
    font-size: 0.92rem;
    line-height: 1.55;
    color: var(--p-surface-500);
  }
}
.lp-kicker {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 1rem;
  padding: 0.3rem 0.75rem 0.3rem 0.6rem;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 9%, transparent);
}
.lp-dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 50%;
  background: currentColor;
  box-shadow: 0 0 0 3px color-mix(in srgb, currentColor 25%, transparent);
}

.lp-error {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 1.25rem;
  padding: 0.65rem 0.8rem;
  border-radius: 0.65rem;
  font-size: 0.85rem;
  color: var(--c-danger-ink);
  background: var(--c-danger-soft);
  border: 1px solid color-mix(in srgb, var(--c-danger) 25%, transparent);
  :deep(ul) {
    margin: 0;
    padding: 0;
    list-style: none;
  }
}

.lp-submit {
  width: 100%;
  margin-top: 1.75rem;
  padding-block: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  border-radius: 0.75rem;
  box-shadow: 0 10px 24px -14px var(--p-primary-color);
  transition:
    transform 0.15s,
    box-shadow 0.2s;
  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 30px -12px var(--p-primary-color);
  }
  &:active {
    transform: translateY(0);
  }
}

/* Logos del grupo junto al formulario solo cuando no se ve el panel visual. */
.lp-companies {
  margin-top: 2.5rem;
  text-align: center;
  @media (min-width: 64rem) {
    display: none;
  }
  ul {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.75rem 1.1rem;
    margin: 1rem 0 0;
    padding: 0;
    list-style: none;
  }
  img {
    height: 1.5rem;
    width: auto;
    opacity: 0.8;
  }
}
.lp-companies-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.62rem;
  font-weight: 600;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--p-surface-500);
  &::before,
  &::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--p-surface-200);
  }
}

.lp-legal {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0;
  font-size: 0.72rem;
  text-align: center;
  color: var(--p-surface-400);
}

@keyframes subir {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
}
@keyframes llenar {
  to {
    transform: scaleX(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .lp-foto,
  .lp-story,
  .lp-form {
    animation: none;
    transition: none;
  }
  .lp-barra.activa::after {
    animation: none;
    transform: scaleX(1);
  }
}
</style>
