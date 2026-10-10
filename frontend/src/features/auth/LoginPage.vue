<template>
  <div class="flex-center flex min-h-dvh w-full py-4">
    <LoginBackground :obstacle="card" />
    <div id="login" ref="card" class="m-auto bg-white/90">
      <Card class="card-login p-4" style="width: min(400px, 90vw)" :class="{ 'opacity-50': loading }">
        <template #title>
          <div class="mb-[15px] w-full text-center">
            <div class="text-[4rem] opacity-80" style="font-family: Faster One; line-height: 1">F D N</div>
            <div class="font-semibold opacity-80">Transportes Fuentes del Norte</div>
          </div>
        </template>
        <template #content>
          <FormKit type="form" :actions="false" @submit-invalid="shake" @submit="handleSubmit">
            <div class="grid gap-[20px]">
              <FormKit type="InputText" name="username" validation="required" prepend="person" placeholder="Usuario" fluid />
              <FormKit type="Password" name="password" validation="required" placeholder="Contraseña" fluid class="mt-2" />
            </div>
            <Button type="submit" label="Aceptar" :loading="loading" class="mt-6 w-full" />
            <div v-if="error" class="flex items-center gap-1">
              <icon name="error-outline" class="text-red-600" />
              <FormKitMessages />
            </div>
          </FormKit>
          <div class="companies">
            <div class="companies-title">Empresas del grupo</div>
            <ul>
              <li v-for="c in COMPANIES" :key="c.name"><img :src="c.logo" :alt="c.name" /></li>
            </ul>
          </div>
        </template>
      </Card>
    </div>
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
import { useSchemaStore } from "@/core/entities/schema";
import { HttpError } from "@/core/http";
import { useLoadingStore } from "@/core/loading";
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
    // Sin schema en localStorage el arranque lo pidió sin sesión (401): se reintenta ya autenticado.
    await useSchemaStore().init();
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
#login {
  z-index: 4;
  & > .card-login {
    box-shadow: 0 0 18px 0 var(--p-surface-800);
  }
  & > div {
    background-color: transparent;
  }
}
.companies {
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--p-surface-300);
  text-align: center;
  .companies-title {
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--p-surface-500);
  }
  ul {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.6rem 0;
    margin: 0.6rem 0 0;
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
      height: 1.9rem;
      width: auto;
      opacity: 0.85;
    }
  }
}
</style>
