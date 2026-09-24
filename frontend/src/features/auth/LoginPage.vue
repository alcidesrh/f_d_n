<template>
  <div class="flex-center flex h-dvh w-dvw">
    <LoginBackground :obstacle="card" />
    <div id="login" ref="card" class="m-auto bg-white/90">
      <Card
        class="card-login p-4"
        style="width: 400px; max-width: 90vw"
        :class="{ 'opacity-50': loading }"
      >
        <template #title>
          <div class="mb-[15px] w-full text-center">
            <div class="text-[4rem] opacity-80" style="font-family: Faster One; line-height: 1">
              F D N
            </div>
            <div class="font-semibold opacity-80">Transportes Fuentes del Norte</div>
          </div>
        </template>
        <template #content>
          <FormKit type="form" :actions="false" @submit-invalid="shake" @submit="handleSubmit">
            <div class="grid gap-[20px]">
              <FormKit
                type="InputText"
                name="username"
                validation="required"
                prepend="person"
                placeholder="Usuario"
                fluid
              />
              <FormKit
                type="Password"
                name="password"
                validation="required"
                placeholder="Contraseña"
                fluid
                class="mt-2"
              />
            </div>
            <Button type="submit" label="Aceptar" :loading="loading" class="mt-6 w-full" />
            <div v-if="error" class="flex items-center gap-1">
              <icon name="error" class="text-red-600" />
              <FormKitMessages />
            </div>
          </FormKit>
        </template>
      </Card>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed, ref, useTemplateRef } from 'vue'
import type { FormKitNode } from '@formkit/core'
import { FormKitMessages } from '@formkit/vue'
import { gsap } from 'gsap'
import { CustomEase } from 'gsap/CustomEase'
import { CustomWiggle } from 'gsap/CustomWiggle'
import { router } from '@/app/router'
import { useSessionStore } from '@/core/auth/session'
import { HttpError } from '@/core/http'
import { useLoadingStore } from '@/core/loading'
import LoginBackground from './LoginBackground.vue'

gsap.registerPlugin(CustomEase, CustomWiggle)

const card = useTemplateRef<HTMLElement>('card')
const session = useSessionStore()
const loadingStore = useLoadingStore()
const loading = computed(() => loadingStore.isLoading('login'))
const error = ref(false)

/** Sacude la tarjeta ante datos inválidos o credenciales rechazadas. */
function shake() {
  gsap.to(card.value, {
    x: -25,
    duration: 1.5,
    ease: CustomWiggle.create('loginWiggle', { wiggles: 10, type: 'easeInOut' }),
  })
}

async function handleSubmit(
  credentials: { username: string; password: string },
  node: FormKitNode,
) {
  error.value = false
  node.clearErrors()
  try {
    await session.login(credentials)
    await router.push({ name: 'dashboard' })
  } catch (cause) {
    error.value = true
    const invalid = cause instanceof HttpError && cause.status === 401
    node.setErrors([
      invalid
        ? 'Usuario o contraseña incorrecto.'
        : cause instanceof Error
          ? cause.message
          : String(cause),
    ])
    shake()
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
</style>
