<!--
  Datos del comprador y de la tarjeta. El pago puede tener pasos de 3-D
  Secure (recolección de datos del dispositivo, desafío del banco en un
  iframe): tras cada uno la página vuelve a enviar el pago con `continuar`.
  Los datos de la tarjeta solo viven en esta página y viajan directo al
  backend, que los pasa a la pasarela sin guardarlos.
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

        <section class="panel">
          <h2 class="m-0 mb-1 text-lg font-semibold">Dirección de la tarjeta</h2>
          <p class="m-0 mb-4 text-sm text-muted-color">La que su banco tiene registrada para la tarjeta (estado de cuenta).</p>
          <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <FormKit type="Select" name="pais" label="País" :options="listaPaises" filter validation="required" fluid autocomplete="country" />
            <FormKit
              type="InputText"
              name="region"
              :label="usaCodigoPostal ? 'Estado o provincia (2 letras)' : 'Departamento o estado'"
              :validation="usaCodigoPostal ? 'required|matches:/^[A-Za-z]{2}$/' : ''"
              :validation-messages="{ matches: 'Use el código de 2 letras (p. ej. CA, TX, ON).' }"
              fluid
              autocomplete="address-level1"
            />
            <FormKit type="InputText" name="ciudad" label="Ciudad" validation="required" fluid autocomplete="address-level2" maxlength="50" />
            <FormKit type="InputText" name="direccion" label="Dirección" validation="required" fluid autocomplete="address-line1" maxlength="60" />
            <FormKit
              v-if="usaCodigoPostal"
              type="InputText"
              name="codigoPostal"
              label="Código postal"
              validation="required"
              fluid
              autocomplete="postal-code"
              maxlength="10"
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

    <DesafioBanco
      v-if="desafio"
      :url="desafio.url"
      :campos="desafio.campos"
      :ancho="desafio.ancho"
      :alto="desafio.alto"
      @completado="desafio.fin($event)"
      @cancelado="desafio.fin(null)"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getNode } from '@formkit/core'
import * as api from '@/api'
import { useCarrito } from '@/carrito'
import DesafioBanco from '@/componentes/DesafioBanco.vue'
import ResumenCarrito from '@/componentes/ResumenCarrito.vue'
import { exigeCodigoPostal, expiraValida, luhn, marca, paises } from '@/modelo'
import { cargarHuella, datosNavegador, recolectarDispositivo } from '@/tresDs'
import type { Catalogos, Comprador, DatosFacturacion, DatosTarjeta, ResultadoPago, SolicitudPago } from '@/tipos'

const router = useRouter()
const carrito = useCarrito()
const catalogos = ref<Catalogos | null>(null)
const datos = ref<Record<string, unknown>>({ nit: 'CF', pais: 'GT' })
const pagando = ref(false)
const error = ref('')

const opciones = (l: Array<{ id: number; nombre: string }> | undefined) => (l ?? []).map((o) => ({ label: o.nombre, value: o.id }))
const naciones = computed(() => opciones(catalogos.value?.naciones))
const documentos = computed(() => opciones(catalogos.value?.tiposDocumento))
const listaPaises = paises()
const usaCodigoPostal = computed(() => exigeCodigoPostal(datos.value.pais))

/** Desafío del banco en curso: `fin` recibe lo que envió el banco (o null si se canceló). */
const desafio = ref<{ url: string; campos: Record<string, string>; ancho: string; alto: string; fin: (d: Record<string, string> | null) => void } | null>(null)

const tarjetaValida = (n: { value: unknown }) => luhn(String(n.value ?? '')) && marca(String(n.value ?? '')) !== null
const vigente = (n: { value: unknown }) => expiraValida(String(n.value ?? ''))
const cvvValido = (n: { value: unknown }) => /^\d{3,4}$/.test(String(n.value ?? '').trim())

onMounted(async () => {
  await carrito.refrescar()
  catalogos.value = await api.catalogos().catch(() => null)
  if (carrito.token) {
    const h = await api.huella(carrito.token).catch(() => null)
    if (h?.script) cargarHuella(h.script)
  }
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
  const facturacion: DatosFacturacion = {
    pais: String(v.pais ?? 'GT'),
    region: v.region ? String(v.region) : undefined,
    ciudad: String(v.ciudad ?? ''),
    direccion: String(v.direccion ?? ''),
    codigoPostal: exigeCodigoPostal(v.pais) && v.codigoPostal ? String(v.codigoPostal) : undefined,
  }
  const token = carrito.token
  const solicitud: SolicitudPago = { comprador, tarjeta, facturacion, navegador: datosNavegador() }
  try {
    let r: ResultadoPago = await api.pagar(token, solicitud)
    // Pasos de 3-D Secure hasta que el banco apruebe (los rechazos llegan como error).
    for (let paso = 0; r.estado !== 'completado'; paso++) {
      if (paso > 4) throw new Error('El banco no terminó la verificación. Intente de nuevo.')
      let continuar: Record<string, string> = {}
      if (r.estado === 'dispositivo') {
        await recolectarDispositivo(r.url, r.campos, r.origenes)
      } else {
        const respuesta = await desafiar(r.url, r.campos, r.ancho, r.alto)
        if (respuesta === null) throw new Error('Canceló la verificación con su banco. No se realizó ningún cobro.')
        continuar = respuesta
      }
      r = await api.pagar(token, { ...solicitud, continuar })
    }
    carrito.olvidar()
    await router.push({ name: 'compra', params: { token } })
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
    if (e instanceof api.ErrorPublico && e.codigo === 'carrito_vencido') await carrito.refrescar()
  } finally {
    desafio.value = null
    pagando.value = false
  }
}

/** Muestra el desafío del banco y espera su resultado (null si el cliente lo cierra). */
function desafiar(url: string, campos: Record<string, string>, ancho: string, alto: string) {
  return new Promise<Record<string, string> | null>((resolve) => {
    desafio.value = {
      url,
      campos,
      ancho,
      alto,
      fin: (d) => {
        desafio.value = null
        resolve(d)
      },
    }
  })
}
</script>
