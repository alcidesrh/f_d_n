<template>
  <div
    class="bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-8 shadow-xl backdrop-blur-md"
  >
    <div class="text-center mb-8">
      <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0 tracking-tight">
        Bienvenido de nuevo
      </h1>
      <p class="text-sm text-surface-500 mt-1">
        Ingresa tus credenciales para acceder a la plataforma
      </p>
    </div>

    <form @submit.prevent="handleLogin" class="flex flex-col gap-5">
      <div class="flex flex-col gap-2">
        <label for="username" class="text-sm font-semibold text-surface-700 dark:text-surface-200">
          Usuario o Correo Electrónico
        </label>
        <InputText
          id="username"
          v-model="username"
          placeholder="admin@fdn.gob.gt"
          class="w-full"
          :class="{ 'p-invalid': errorMessage }"
          required
        />
      </div>

      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <label
            for="password"
            class="text-sm font-semibold text-surface-700 dark:text-surface-200"
          >
            Contraseña
          </label>
          <a
            href="#"
            class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 font-medium"
          >
            ¿Olvidaste tu contraseña?
          </a>
        </div>
        <Password
          id="password"
          v-model="password"
          placeholder="••••••••"
          :toggleMask="true"
          :feedback="false"
          class="w-full"
          inputClass="w-full"
          required
        />
      </div>

      <div class="flex items-center justify-between text-sm">
        <div class="flex items-center gap-2">
          <Checkbox id="remember" v-model="rememberMe" :binary="true" />
          <label
            for="remember"
            class="text-sm text-surface-600 dark:text-surface-400 cursor-pointer"
          >
            Recordar mi sesión
          </label>
        </div>
      </div>

      <div
        v-if="errorMessage"
        class="p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-600 dark:text-red-400 text-xs flex items-center gap-2"
      >
        <AppIcon name="alert" :size="16" />
        <span>{{ errorMessage }}</span>
      </div>

      <Button
        type="submit"
        label="Iniciar Sesión"
        icon="pi pi-sign-in"
        :loading="loading"
        class="w-full py-3"
      />
    </form>

    <div class="mt-8 pt-6 border-t border-surface-200 dark:border-surface-800 text-center">
      <p class="text-xs text-surface-500 mb-3">Acceso rápido de prueba:</p>
      <div class="flex gap-2 justify-center">
        <Button
          label="Demo Administrador"
          severity="secondary"
          size="small"
          outlined
          @click="fillDemo('admin@fdn.gob.gt')"
        />
        <Button
          label="Demo Operador"
          severity="secondary"
          size="small"
          outlined
          @click="fillDemo('operador@fdn.gob.gt')"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const router = useRouter()

const username = ref('')
const password = ref('')
const rememberMe = ref(true)
const loading = ref(false)
const errorMessage = ref('')

function fillDemo(user: string) {
  username.value = user
  password.value = 'admin123'
}

async function handleLogin() {
  errorMessage.value = ''
  if (!username.value || !password.value) {
    errorMessage.value = 'Por favor completa todos los campos'
    return
  }

  loading.value = true
  try {
    // Simulate auth API call
    await new Promise((resolve) => setTimeout(resolve, 800))
    // Redirect to home/dashboard page upon successful login
    router.push({ name: 'dashboard' })
  } catch (err: unknown) {
    errorMessage.value = err instanceof Error ? err.message : 'Error al iniciar sesión'
  } finally {
    loading.value = false
  }
}
</script>
