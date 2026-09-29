<!--
  Datos del comprador y de la tarjeta. Si el banco pide 3-D Secure, el
  navegador va a su página de verificación y vuelve a /compra/{token}. Los
  datos de la tarjeta viajan directo al backend, que los pasa a la pasarela
  sin guardarlos.
-->
<template>
  <div class="mx-auto max-w-6xl px-4 py-6">
    <div v-if="carrito.vacio" class="panel text-center">
      <p class="m-0">No tiene asientos apartados (o el tiempo para pagar venció).</p>
      <RouterLink :to="{ name: 'inicio' }" class="mt-2 inline-block">Buscar una salida</RouterLink>
    </div>

    <div v-else class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
      <FormKit
        id="pago"
        v-model="datos"
        type="form"
        :actions="false"
        :incomplete-message="'Revise los campos marcados.'"
        form-class="flex flex-col gap-4"
        @submit="pagar"
      >
        <section class="panel">
          <h2 class="m-0 mb-4 text-lg font-semibold">Sus datos</h2>
          <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <FormKit type="InputText" name="nombre" label="Nombre(s)" validation="required" fluid autocomplete="given-name" />
            <FormKit type="InputText" name="apellido" label="Apellido(s)" fluid autocomplete="family-name" />
            <FormKit
              type="InputText"
              name="email"
              label="Correo electrónico"
              help="Ahí le enviaremos su boleto y la factura."
              validation="required|email"
              fluid
              autocomplete="email"
              inputmode="email"
            />
            <FormKit type="InputText" name="telefono" label="Teléfono" fluid autocomplete="tel" inputmode="tel" />
            <FormKit
              type="InputText"
              name="nit"
              label="NIT para la factura"
              help="Sin guion. Deje CF si no necesita factura a su nombre."
              validation="required"
              fluid
            />
            <FormKit type="Select" name="nacionalidad" label="Nacionalidad (opcional)" :options="naciones" show-clear filter fluid />
            <FormKit type="Select" name="tipoDocumento" label="Documento (opcional)" :options="documentos" show-clear fluid />
            <FormKit type="InputText" name="numeroDocumento" label="Número de documento" fluid />
          </div>
        </section>

        <section class="panel">
          <h2 class="m-0 mb-1 flex items-center gap-2 text-lg font-semibold"><icon name="credit-card" /> Tarjeta</h2>
          <p class="m-0 mb-4 text-sm text-muted-color">Visa o Mastercard, de crédito o débito. Su banco puede pedirle una verificación (3-D Secure).</p>
          <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <FormKit type="InputText" name="titular" label="Nombre en la tarjeta" validation="required" fluid autocomplete="cc-name" />
            <FormKit
              type="InputText"
              name="numero"
              label="Número de tarjeta"
              validation="required|tarjeta"
              :validation-rules="{ tarjeta: tarjetaValida }"
              :validation-messages="{ tarjeta: 'Número inválido o tarjeta no aceptada (solo Visa y Mastercard).' }"
              fluid
              autocomplete="cc-number"
              inputmode="numeric"
            />
            <FormKit
              type="InputMask"
              name="expira"
              label="Vence (MM/AA)"
              mask="99/99"
              placeholder="MM/AA"
              validation="required|vigente"
              :validation-rules="{ vigente: vigente }"
              :validation-messages="{ vigente: 'Fecha de vencimiento inválida.' }"
              fluid
              autocomplete="cc-exp"
            />
            <FormKit
              type="InputText"
              name="cvv"
              label="Código de seguridad (CVV)"
              validation="required|cvv"
              :validation-rules="{ cvv: cvvValido }"
              :validation-messages="{ cvv: '3 o 4 dígitos.' }"
              fluid
              autocomplete="cc-csc"
              inputmode="numeric"
              maxlength="4"
            />
          </div>
        </section>

        <Message v-if="error" severity="error" :closable="false">
          <div class="font-semibold">No se realizó la compra</div>
          <div>{{ error }}</div>
        </Message>

        <div class="lg:hidden">
          <Button type="submit" size="large" fluid :loading="pagando" :label="`Pagar ${carrito.carrito?.total?.texto ?? ''}`">
            <template #icon><icon name="lock" class="mr-1" /></template>
          </Button>
        </div>
      </FormKit>

      <aside class="panel flex flex-col gap-3 lg:sticky lg:top-20">
        <ResumenCarrito @vencio="vencio" />
        <div class="hidden lg:block">
          <Button size="large" fluid :loading="pagando" :label="`Pagar ${carrito.carrito?.total?.texto ?? ''}`" @click="enviar">
            <template #icon><icon name="lock" class="mr-1" /></template>
          </Button>
        </div>
        <p class="m-0 flex items-center gap-1 text-xs text-muted-color"><icon name="lock" size="0.9rem" /> Conexión cifrada. No guardamos los datos de su tarjeta.</p>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getNode } from '@formkit/core'
import * as api from '@/api'
import { useCarrito } from '@/carrito'
import ResumenCarrito from '@/componentes/ResumenCarrito.vue'
import { expiraValida, luhn, marca } from '@/modelo'
import type { Catalogos, Comprador, DatosTarjeta } from '@/tipos'

const router = useRouter()
const carrito = useCarrito()
const catalogos = ref<Catalogos | null>(null)
const datos = ref<Record<string, unknown>>({ nit: 'CF' })
const pagando = ref(false)
const error = ref('')

const opciones = (l: Array<{ id: number; nombre: string }> | undefined) => (l ?? []).map((o) => ({ label: o.nombre, value: o.id }))
const naciones = computed(() => opciones(catalogos.value?.naciones))
const documentos = computed(() => opciones(catalogos.value?.tiposDocumento))

const tarjetaValida = (n: { value: unknown }) => luhn(String(n.value ?? '')) && marca(String(n.value ?? '')) !== null
const vigente = (n: { value: unknown }) => expiraValida(String(n.value ?? ''))
const cvvValido = (n: { value: unknown }) => /^\d{3,4}$/.test(String(n.value ?? '').trim())

onMounted(async () => {
  await carrito.refrescar()
  catalogos.value = await api.catalogos().catch(() => null)
})

/** Botón del resumen (fuera del formulario en escritorio). */
function enviar() {
  getNode('pago')?.submit()
}

async function vencio() {
  await carrito.refrescar()
}

async function pagar(v: Record<string, unknown>) {
  if (!carrito.token) return
  pagando.value = true
  error.value = ''
  const comprador: Comprador = {
    nombre: String(v.nombre ?? ''),
    apellido: v.apellido ? String(v.apellido) : undefined,
    email: String(v.email ?? ''),
    telefono: v.telefono ? String(v.telefono) : undefined,
    nit: String(v.nit ?? 'CF'),
    tipoDocumento: (v.tipoDocumento as number | null) ?? null,
    numeroDocumento: v.numeroDocumento ? String(v.numeroDocumento) : undefined,
    nacionalidad: (v.nacionalidad as number | null) ?? null,
  }
  const tarjeta: DatosTarjeta = {
    numero: String(v.numero ?? ''),
    expira: String(v.expira ?? ''),
    cvv: String(v.cvv ?? ''),
    titular: String(v.titular ?? ''),
  }
  const token = carrito.token
  try {
    const r = await api.pagar(token, comprador, tarjeta)
    if (r.estado === 'completado') {
      carrito.olvidar()
      await router.push({ name: 'compra', params: { token } })
      return
    }
    autenticar(r.url, r.campos)
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
    if (e instanceof api.ErrorPublico && e.codigo === 'carrito_vencido') await carrito.refrescar()
  } finally {
    pagando.value = false
  }
}

/** 3-D Secure: el navegador envía los campos al banco (POST de formulario). */
function autenticar(url: string, campos: Record<string, string>) {
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = url
  for (const [nombre, valor] of Object.entries(campos)) {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = nombre
    input.value = valor
    form.appendChild(input)
  }
  document.body.appendChild(form)
  form.submit()
}
</script>
