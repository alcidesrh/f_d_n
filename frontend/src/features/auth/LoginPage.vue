<template>
  <div class="lg-page">
    <aside class="lg-hero" :style="{ backgroundImage: `url('${heroImage}')` }" aria-hidden="true">
      <div class="lg-hero-shade" />
      <div class="lg-hero-top">
        <span class="lg-logo">F D N</span>
      </div>
      <div class="lg-hero-body">
        <p class="lg-kicker">Transportes Fuentes del Norte</p>
        <h2>Cada ruta,<br />bajo control.</h2>
        <p class="lg-lead">Venta de pasajes, salidas y operación de estaciones en una sola plataforma.</p>
      </div>
      <div class="lg-hero-foot">
        <img v-for="c in COMPANIES" :key="c.name" :src="c.logo" :alt="c.name" />
      </div>
    </aside>

    <main id="login" ref="card" class="lg-panel" :class="{ 'is-loading': loading }">
      <div class="lg-form-wrap">
        <div class="lg-mobile-brand">
          <span class="lg-logo">F D N</span>
          <span>Transportes Fuentes del Norte</span>
        </div>

        <header class="lg-head">
          <h1>Iniciar sesión</h1>
          <p>Accede a tu espacio de trabajo con tus credenciales.</p>
        </header>

        <FormKit type="form" :actions="false" @submit-invalid="shake" @submit="handleSubmit">
          <div class="grid gap-5">
            <FormKit type="InputText" name="username" validation="required" prepend="person" autocapitalize="none" autocomplete="username" autocorrect="off" spellcheck="false" :style="{ textTransform: 'none' }" placeholder="Usuario" fluid />
            <FormKit type="Password" name="password" validation="required" placeholder="Contraseña" fluid />
          </div>
          <Button type="submit" label="Continuar" icon="pi pi-arrow-right" icon-pos="right" :loading="loading" class="lg-submit" />
          <div v-if="error" class="mt-4 flex items-center gap-1 text-sm" role="alert">
            <icon name="error-outline" class="text-red-600" />
            <FormKitMessages />
          </div>
        </FormKit>

        <footer class="lg-foot">© {{ year }} Transportes Fuentes del Norte</footer>
      </div>
    </main>
  </div>
</template>

<script lang="ts" setup>
import { computed, ref, useTemplateRef } from "vue";
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

/** Una de las fotos del servicio, al azar en cada visita. */
const heroImage = `images/login${Math.floor(Math.random() * 10) + 1}.png`;
const year = new Date().getFullYear();

const route = useRoute();
const card = useTemplateRef<HTMLElement>("card");
const session = useSessionStore();
const loadingStore = useLoadingStore();
const loading = computed(() => loadingStore.isLoading("login"));
const error = ref(false);

/** Sacude el formulario ante datos inválidos o credenciales rechazadas. */
function shake() {
  gsap.to(card.value, {
    x: -25,
    duration: 1.5,
    ease: CustomWiggle.create("loginWiggle", { wiggles: 10, type: "easeInOut" }),
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
.lg-page {
  display: grid;
  min-height: 100dvh;
  background: var(--p-surface-0);
}

/* Panel de marca: foto con velo azul noche. Solo desde `lg`. */
.lg-hero {
  display: none;
  position: relative;
  flex-direction: column;
  justify-content: space-between;
  padding: 3rem;
  color: #fff;
  background-size: cover;
  background-position: center;
  overflow: hidden;
  .lg-hero-shade {
    position: absolute;
    inset: 0;
    background:
      linear-gradient(180deg, rgb(8 14 32 / 0.65) 0%, rgb(8 14 32 / 0.35) 40%, rgb(8 14 32 / 0.92) 100%),
      linear-gradient(90deg, rgb(12 30 90 / 0.55), transparent 70%);
  }
  & > :not(.lg-hero-shade) {
    position: relative;
  }
  .lg-hero-body {
    max-width: 30rem;
    animation: lg-subir 0.9s 0.15s cubic-bezier(0.16, 1, 0.3, 1) both;
  }
  .lg-kicker {
    margin: 0 0 1rem;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.24em;
    text-transform: uppercase;
    color: rgb(255 255 255 / 0.7);
    &::before {
      content: "";
      display: inline-block;
      width: 2rem;
      height: 1px;
      margin-right: 0.8rem;
      vertical-align: middle;
      background: rgb(255 255 255 / 0.6);
    }
  }
  h2 {
    margin: 0;
    font-size: clamp(2.4rem, 4vw, 3.6rem);
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: -0.03em;
  }
  .lg-lead {
    margin: 1.25rem 0 0;
    font-size: 1rem;
    line-height: 1.6;
    color: rgb(255 255 255 / 0.75);
  }
  .lg-hero-foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1.25rem 1.75rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgb(255 255 255 / 0.18);
    img {
      height: 1.6rem;
      width: auto;
      filter: brightness(0) invert(1);
      opacity: 0.7;
    }
  }
}

.lg-logo {
  font-family: "Faster One";
  font-size: 2rem;
  line-height: 1;
  white-space: nowrap;
}

/* Panel del formulario. */
.lg-panel {
  display: grid;
  place-items: center;
  padding: 2rem 1.5rem;
  transition: opacity 0.2s;
  &.is-loading {
    opacity: 0.65;
  }
}
.lg-form-wrap {
  width: min(380px, 100%);
  animation: lg-subir 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.lg-mobile-brand {
  display: grid;
  gap: 0.35rem;
  margin-bottom: 2.5rem;
  font-size: 0.65rem;
  font-weight: 600;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--p-surface-500);
  .lg-logo {
    font-size: 2.6rem;
    letter-spacing: 0;
    text-transform: none;
    color: var(--p-surface-900);
  }
}
.lg-head {
  margin-bottom: 2rem;
  h1 {
    margin: 0;
    font-size: 1.9rem;
    font-weight: 700;
    letter-spacing: -0.025em;
    color: var(--p-surface-900);
  }
  p {
    margin: 0.5rem 0 0;
    font-size: 0.95rem;
    line-height: 1.5;
    color: var(--p-surface-500);
  }
}
.lg-submit {
  width: 100%;
  margin-top: 1.75rem;
  padding-block: 0.8rem;
  font-weight: 600;
  border-radius: 0.75rem;
  transition:
    transform 0.15s,
    box-shadow 0.15s;
  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px -12px var(--p-primary-color);
  }
}
.lg-foot {
  margin-top: 3rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--p-surface-200);
  font-size: 0.75rem;
  color: var(--p-surface-400);
}

@media (min-width: 64rem) {
  .lg-page {
    grid-template-columns: 1.15fr 1fr;
  }
  .lg-hero {
    display: flex;
  }
  .lg-mobile-brand {
    display: none;
  }
}

@keyframes lg-subir {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
}
@media (prefers-reduced-motion: reduce) {
  .lg-form-wrap,
  .lg-hero .lg-hero-body {
    animation: none;
  }
}
</style>
