<template>
  <div class="flex min-h-dvh w-full items-center justify-center px-4 py-8">
    <LoginBackground :obstacle="card" />
    <main id="login" ref="card" class="login-shell" :class="{ 'is-loading': loading }">
      <header class="lg-brand">
        <div class="lg-mark" aria-hidden="true">
          <icon name="directions-bus" />
        </div>
        <div class="lg-name">F D N</div>
        <div class="lg-sub">Transportes Fuentes del Norte</div>
      </header>

      <section class="lg-welcome">
        <h2>Bienvenido</h2>
        <p>Ingresa con tu cuenta para continuar.</p>
      </section>

      <FormKit type="form" :actions="false" @submit-invalid="shake" @submit="handleSubmit">
        <div class="grid gap-5">
          <FormKit type="InputText" name="username" validation="required" prepend="person" autocapitalize="none" autocomplete="username" autocorrect="off" spellcheck="false" :style="{ textTransform: 'none' }" placeholder="Usuario" fluid />
          <FormKit type="Password" name="password" validation="required" placeholder="Contraseña" fluid />
        </div>
        <Button type="submit" label="Iniciar sesión" icon="pi pi-arrow-right" icon-pos="right" :loading="loading" class="lg-submit" />
        <div v-if="error" class="mt-3 flex items-center gap-1 text-sm" role="alert">
          <icon name="error-outline" class="text-red-600" />
          <FormKitMessages />
        </div>
      </FormKit>

      <footer class="companies">
        <div class="companies-title"><span>Empresas del grupo</span></div>
        <ul>
          <li v-for="c in COMPANIES" :key="c.name"><img :src="c.logo" :alt="c.name" class="dark:opacity-75 dark:brightness-0 dark:invert" /></li>
        </ul>
      </footer>
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
import LoginBackground from "./LoginBackground.vue";

gsap.registerPlugin(CustomEase, CustomWiggle);

/** Empresas que operan el servicio. */
const COMPANIES = [
  { name: "La Pionera", logo: "images/logos/copiloto/lapionera5.png" },
  { name: "Rosita", logo: "images/logos/copiloto/rosita5.png" },
  { name: "Maya de Oro", logo: "images/logos/copiloto/mayadeoro5.png" },
  { name: "Starbus", logo: "images/logos/copiloto/starbus5.png" },
  { name: "Corporación La Pionera", logo: "images/logos/copiloto/corporacionlapionera5.png" },
];

const route = useRoute();
const card = useTemplateRef<HTMLElement>("card");
const session = useSessionStore();
const loadingStore = useLoadingStore();
const loading = computed(() => loadingStore.isLoading("login"));
const error = ref(false);

/** Sacude la tarjeta ante datos inválidos o credenciales rechazadas. */
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
.login-shell {
  position: relative;
  z-index: 4;
  width: min(420px, 100%);
  padding: 2.25rem 2rem 1.75rem;
  border-radius: 1.5rem;
  background: color-mix(in srgb, var(--p-surface-0) 80%, transparent);
  border: 1px solid color-mix(in srgb, var(--p-surface-0) 55%, transparent);
  box-shadow:
    0 30px 60px -20px rgb(0 0 0 / 0.45),
    0 0 0 1px color-mix(in srgb, var(--p-surface-900) 6%, transparent);
  backdrop-filter: blur(22px) saturate(1.4);
  -webkit-backdrop-filter: blur(22px) saturate(1.4);
  animation: entrada 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
  transition: opacity 0.2s;
  &.is-loading {
    opacity: 0.6;
  }
  /* Filete de luz en el borde superior. */
  &::before {
    content: "";
    position: absolute;
    inset: 0 2rem auto;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--p-primary-color), transparent);
    opacity: 0.8;
  }
}

.lg-brand {
  text-align: center;
  .lg-mark {
    display: grid;
    margin-inline: auto;
    place-items: center;
    width: 3rem;
    height: 3rem;
    margin-bottom: 0.75rem;
    border-radius: 0.9rem;
    font-size: 1.7rem;
    color: var(--p-primary-contrast-color);
    background: linear-gradient(135deg, var(--p-primary-400), var(--p-primary-600));
    box-shadow: 0 8px 20px -6px var(--p-primary-color);
  }
  .lg-name {
    margin: 0;
    white-space: nowrap;
    font-family: "Faster One";
    font-size: 3.4rem;
    font-weight: 400;
    line-height: 1;
    color: var(--p-surface-900);
  }
  .lg-sub {
    margin: 0.4rem 0 0;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--p-surface-500);
  }
}

.lg-welcome {
  margin: 1.75rem 0 1.25rem;
  h2 {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--p-surface-900);
  }
  p {
    margin: 0.2rem 0 0;
    font-size: 0.875rem;
    color: var(--p-surface-500);
  }
}

.lg-submit {
  width: 100%;
  margin-top: 1.5rem;
  padding-block: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  border-radius: 0.75rem;
  transition:
    transform 0.15s,
    box-shadow 0.15s;
  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px -10px var(--p-primary-color);
  }
}

.companies {
  margin-top: 1.75rem;
  text-align: center;
  .companies-title {
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
      background: var(--p-surface-300);
    }
  }
  ul {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.6rem 0;
    margin: 0.8rem 0 0;
    padding: 0;
    list-style: none;
  }
  li {
    display: flex;
    align-items: center;
    padding: 0 0.6rem;
    & + li {
      border-left: 1px solid var(--p-surface-300);
    }
    img {
      height: 1.7rem;
      width: auto;
      opacity: 0.8;
      transition: opacity 0.2s;
      &:hover {
        opacity: 1;
      }
    }
  }
}

@keyframes entrada {
  from {
    opacity: 0;
    transform: translateY(18px) scale(0.98);
  }
}

@media (prefers-reduced-motion: reduce) {
  .login-shell {
    animation: none;
  }
}
</style>
