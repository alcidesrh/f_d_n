<!--
  Modificar un viaje comprado en línea («Mis compras»): por cada asiento
  comprado, cambiar la hora (otra salida), la ruta y el asiento (también de
  otra clase), y agregar asientos. No se quitan asientos: el cliente no
  anula ni cancela. Antes de confirmar se cotiza: si un asiento pasa a uno
  más caro, o se agregan asientos, o hay penalización, se paga con tarjeta
  (3-D Secure, como en la compra); si pasa a uno más barato, no se devuelve
  la diferencia. La tarjeta solo vive en esta página.
-->
<template>
  <div class="contenedor flex max-w-3xl flex-col gap-4 py-6 md:py-10">
    <RouterLink :to="{ name: 'misCompras', params: { idioma: locale } }" class="flex items-center gap-1 text-sm font-medium text-marca-700 no-underline">
      <icon name="arrow-left" size="1rem" />{{ t('misCompras.titulo') }}
    </RouterLink>

    <Skeleton v-if="cargando" height="20rem" border-radius="1rem" />

    <section v-else-if="!viaje" class="panel flex flex-col items-center gap-3 py-8 text-center">
      <icon name="alert" size="2.5rem" class="text-acento-500" />
      <p class="m-0 font-medium">{{ error || t('modificar.noDisponible') }}</p>
      <RouterLink :to="{ name: 'misCompras', params: { idioma: locale } }" class="no-underline"><Button :label="t('misCompras.titulo')" /></RouterLink>
    </section>

    <!-- Hecho -->
    <section v-else-if="resultado" class="panel flex flex-col gap-4">
      <div class="flex items-start gap-3">
        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700"><icon name="check" size="1.8rem" /></span>
        <div>
          <h1 class="m-0 text-2xl font-bold">{{ t('modificar.listo') }}</h1>
          <p class="m-0 mt-1 text-muted-color">{{ t('modificar.listoTexto') }}</p>
          <p v-if="resultado.cobrado.centavos > 0" class="m-0 mt-1 text-sm">{{ t('modificar.cobrado', { monto: resultado.cobrado.texto }) }}</p>
          <p v-if="resultado.refacturada" class="m-0 mt-1 text-sm">{{ t('modificar.refacturada') }}</p>
          <p v-else-if="resultado.errorFactura" class="m-0 mt-1 text-sm text-orange-700">{{ t('modificar.facturaPendiente') }}</p>
        </div>
      </div>
      <RouterLink :to="{ name: 'misCompras', params: { idioma: locale } }" class="no-underline"><Button :label="t('modificar.verCompras')" /></RouterLink>
    </section>

    <template v-else>
      <header>
        <h1 class="m-0 text-2xl font-bold text-marca-900">{{ t('modificar.titulo') }}</h1>
        <p class="m-0 mt-1 text-muted-color">{{ viaje.origen }} → {{ viaje.destino }} · {{ t('compra.numero') }} {{ viaje.codigoBarras }}</p>
        <p v-if="viaje.limiteModificacion" class="m-0 mt-1 text-sm text-muted-color">{{ t('misCompras.hasta', { fecha: fechaHora(viaje.limiteModificacion) }) }}</p>
      </header>

      <section class="panel flex flex-col gap-3">
        <h2 class="m-0 text-lg font-semibold">{{ t('modificar.susAsientos') }}</h2>
        <ul class="m-0 flex list-none flex-col gap-3 p-0">
          <li v-for="b in viaje.boletos" :key="b.id" class="flex flex-col gap-2 rounded-xl bg-surface-50 p-3 ring-1 ring-surface-200">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div :class="{ 'text-muted-color line-through': cambios[b.id] }">
                <p class="m-0 font-semibold">{{ t('pago.asiento', { numero: b.asiento }) }} · {{ t('modificar.clase', { clase: b.clase }) }}<span v-if="b.precio" class="ml-2 font-normal">{{ b.precio.texto }}</span></p>
                <p class="m-0 text-sm">{{ b.trayecto.origen.nombre }} → {{ b.trayecto.destino.nombre }} · {{ fechaHora(b.salida.salidaOrigen) }}</p>
              </div>
              <Button v-if="!cambios[b.id]" size="small" outlined :disabled="!b.modificable || ocupado" @click="abrir({ tipo: 'cambio', boleto: b })">
                <icon name="edit" size="1rem" /><span>{{ t('modificar.cambiar') }}</span>
              </Button>
            </div>
            <p v-if="!b.modificable && b.motivo" class="m-0 text-xs text-muted-color">{{ b.motivo }}</p>
            <p v-else-if="!cambios[b.id]" class="m-0 text-xs text-muted-color">{{ t('modificar.restantes', restantes(b)) }}</p>
            <div v-if="cambios[b.id]" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-acento-100/60 px-3 py-2">
              <div>
                <p class="m-0 flex items-center gap-1 font-semibold text-acento-700"><icon name="arrow-right" size="1rem" />{{ t('pago.asiento', { numero: cambios[b.id]!.numero }) }} · {{ t('modificar.clase', { clase: cambios[b.id]!.clase }) }}</p>
                <p class="m-0 text-sm">{{ cambios[b.id]!.origen }} → {{ cambios[b.id]!.destino }} · {{ fechaHora(cambios[b.id]!.hora) }}</p>
              </div>
              <Button size="small" text severity="secondary" :disabled="ocupado" :label="t('modificar.deshacer')" @click="deshacer(b.id)" />
            </div>
          </li>
          <li v-for="(n, i) in nuevos" :key="`n${i}`" class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-emerald-50 p-3 ring-1 ring-emerald-200">
            <div>
              <p class="m-0 flex items-center gap-1 font-semibold text-emerald-800"><icon name="plus" size="1rem" />{{ t('pago.asiento', { numero: n.numero }) }} · {{ t('modificar.clase', { clase: n.clase }) }}<span class="ml-2 font-normal">{{ quetzales(n.centavos, region) }}</span></p>
              <p class="m-0 text-sm">{{ n.origen }} → {{ n.destino }} · {{ fechaHora(n.hora) }}</p>
            </div>
            <Button size="small" text severity="secondary" :disabled="ocupado" :label="t('modificar.quitar')" @click="quitarNuevo(i)" />
          </li>
        </ul>
        <div>
          <Button outlined severity="secondary" :disabled="ocupado" @click="abrir({ tipo: 'nuevo' })"><icon name="plus" size="1.1rem" /><span>{{ t('modificar.agregar') }}</span></Button>
        </div>
        <p class="m-0 text-xs text-muted-color">{{ t('modificar.sinQuitar') }}</p>
      </section>

      <Message v-if="error" severity="error" :closable="false">
        <div>{{ error }}</div>
        <div v-if="errorDetalle" class="mt-1 text-sm opacity-90">{{ errorDetalle }}</div>
      </Message>

      <!-- Paso 1: revisar lo que cuesta -->
      <div v-if="!cotizacion">
        <Button size="large" class="boton-compra w-full sm:w-auto" :disabled="!hayCambios" :loading="cotizando" :label="t('modificar.revisar')" @click="cotizar" />
      </div>

      <!-- Paso 2: desglose, tarjeta si hay que pagar, confirmar -->
      <template v-else>
        <section class="panel flex flex-col gap-3">
          <h2 class="m-0 text-lg font-semibold">{{ t('modificar.resumen') }}</h2>
          <ul class="m-0 flex list-none flex-col gap-2 p-0 text-sm">
            <li v-for="c in cotizacion.cambios" :key="c.boleto" class="flex flex-col gap-0.5">
              <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                <span>{{ t('modificar.lineaCambio', { de: c.desde.asiento, a: c.hacia.asiento }) }} <span class="text-muted-color">({{ c.pagado.texto }} → {{ c.nuevo.texto }})</span></span>
                <span class="font-medium">{{ c.sinReembolso ? t('modificar.sinDevolucion') : c.diferencia.texto }}</span>
              </div>
              <span v-if="c.sinReembolso" class="text-xs text-orange-700">{{ t('modificar.avisoMenorPrecio', { monto: c.sinReembolso.texto }) }}</span>
              <span v-else-if="c.diferencia.centavos > 0" class="text-xs text-muted-color">{{ t('modificar.avisoMayorPrecio', { monto: c.diferencia.texto }) }}</span>
            </li>
            <li v-for="n in cotizacion.nuevos" :key="`${n.salida}-${n.asientoId}`" class="flex flex-wrap items-baseline justify-between gap-x-4">
              <span>{{ t('modificar.lineaNuevo', { numero: n.asiento }) }}</span><span class="font-medium">{{ n.precio.texto }}</span>
            </li>
            <li v-if="cotizacion.penalizacion.centavos > 0" class="flex flex-wrap items-baseline justify-between gap-x-4">
              <span>{{ t('modificar.penalizacion') }}</span><span class="font-medium">{{ cotizacion.penalizacion.texto }}</span>
            </li>
            <li class="flex flex-wrap items-baseline justify-between gap-x-4 border-t border-surface-200 pt-2 text-base font-bold">
              <span>{{ t('modificar.total') }}</span><span>{{ cotizacion.total.texto }}</span>
            </li>
          </ul>
          <p class="m-0 text-xs text-muted-color">{{ t('modificar.avisoFactura') }}</p>
        </section>

        <form class="flex flex-col gap-4" @submit.prevent="confirmar">
          <section v-if="cotizacion.requierePago" class="panel flex flex-col gap-3">
            <h2 class="m-0 flex items-center gap-2 text-lg font-semibold"><icon name="credit-card" class="text-marca-700" />{{ t('pago.tarjeta') }}</h2>
            <p class="m-0 text-sm text-muted-color">{{ t('pago.tarjetaAyuda') }}</p>
            <div class="grid grid-cols-2 gap-3">
              <label class="col-span-2 flex flex-col gap-1.5 md:col-span-1"><span class="text-sm font-medium">{{ t('pago.titular') }}</span><InputText v-model="tarjeta.titular" required autocomplete="cc-name" fluid /></label>
              <label class="col-span-2 flex flex-col gap-1.5 md:col-span-1"><span class="text-sm font-medium">{{ t('pago.numero') }}</span><InputText v-model="tarjeta.numero" required autocomplete="cc-number" inputmode="numeric" :invalid="!!tarjeta.numero && !numeroValido" fluid /></label>
              <label class="flex flex-col gap-1.5"><span class="text-sm font-medium">{{ t('pago.vence') }}</span><InputMask v-model="tarjeta.expira" mask="99/99" placeholder="MM/AA" required autocomplete="cc-exp" inputmode="numeric" :invalid="!!tarjeta.expira && !expiraOk" fluid /></label>
              <label class="flex flex-col gap-1.5"><span class="text-sm font-medium">{{ t('pago.cvv') }}</span><InputText v-model="tarjeta.cvv" required autocomplete="cc-csc" inputmode="numeric" maxlength="4" fluid /></label>
            </div>
            <h3 class="m-0 mt-2 text-base font-semibold">{{ t('pago.direccion') }}</h3>
            <p class="m-0 text-sm text-muted-color">{{ t('pago.direccionAyuda') }}</p>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <label class="flex flex-col gap-1.5"><span class="text-sm font-medium">{{ t('pago.pais') }}</span><Select v-model="direccion.pais" :options="listaPaises" option-label="label" option-value="value" filter fluid /></label>
              <!-- Departamento y ciudad de una lista, como en la compra (ADR-041); texto libre donde no hay datos. -->
              <label v-if="regiones.length" class="flex flex-col gap-1.5"><span class="text-sm font-medium">{{ t('pago.region') }}</span><Select v-model="direccion.regionId" :options="opcionesRegion" option-label="label" option-value="value" filter :invalid="revisar && direccion.regionId === null" fluid /></label>
              <label v-else class="flex flex-col gap-1.5"><span class="text-sm font-medium">{{ usaCodigoPostal ? t('pago.regionCodigo') : t('pago.region') }}</span><InputText v-model="direccion.region" :required="usaCodigoPostal" autocomplete="address-level1" maxlength="50" fluid /></label>
              <label v-if="ciudades.length" class="flex flex-col gap-1.5"><span class="text-sm font-medium">{{ t('pago.ciudad') }}</span><Select v-model="direccion.ciudad" :options="ciudades" option-label="label" option-value="value" filter :invalid="revisar && !direccion.ciudad" fluid /></label>
              <label v-else class="flex flex-col gap-1.5"><span class="text-sm font-medium">{{ t('pago.ciudad') }}</span><InputText v-model="direccion.ciudad" required autocomplete="address-level2" maxlength="50" fluid /></label>
              <label class="flex flex-col gap-1.5"><span class="text-sm font-medium">{{ t('pago.calle') }}</span><InputText v-model="direccion.direccion" required autocomplete="address-line1" maxlength="60" fluid /></label>
              <label v-if="usaCodigoPostal" class="flex flex-col gap-1.5"><span class="text-sm font-medium">{{ t('pago.codigoPostal') }}</span><InputText v-model="direccion.codigoPostal" required autocomplete="postal-code" maxlength="10" fluid /></label>
            </div>
          </section>

          <p class="m-0 text-sm text-slate-700">
            <i18n-t keypath="modificar.aceptaTerminos" tag="span" scope="global">
              <template #terminos>
                <RouterLink :to="{ name: 'politicas', params: { idioma: locale } }" target="_blank" class="font-semibold text-marca-700 underline">{{ t('pago.terminos') }}</RouterLink>
              </template>
            </i18n-t>
          </p>

          <div class="flex flex-col gap-2 sm:flex-row">
            <Button type="submit" size="large" class="boton-compra w-full sm:w-auto" :loading="confirmando">
              <icon :name="cotizacion.requierePago ? 'lock' : 'check'" size="1.1rem" />
              <span>{{ cotizacion.requierePago ? t('modificar.pagar', { total: cotizacion.total.texto }) : t('modificar.confirmar') }}</span>
            </Button>
            <Button type="button" severity="secondary" outlined size="large" class="w-full sm:w-auto" :disabled="confirmando" :label="t('modificar.seguirEditando')" @click="cotizacion = null" />
          </div>
        </form>
      </template>
    </template>

    <Dialog v-model:visible="selectorAbierto" modal :header="tituloSelector" :draggable="false" class="w-[min(46rem,calc(100vw-1rem))]" :pt="{ content: { class: 'pb-4' } }">
      <SelectorAsientoViaje v-if="selector && viaje" :key="selectorClave" :origen="selectorOrigen.origen" :destino="selectorOrigen.destino" :dia="selectorOrigen.dia" :tomados="tomados" @elegido="elegir" />
    </Dialog>

    <Dialog :visible="confirmando && !desafio" modal :closable="false" :draggable="false" :show-header="false" class="w-[min(22rem,calc(100vw-2rem))]">
      <div class="flex flex-col items-center gap-3 pt-6 text-center">
        <ProgressSpinner style="width: 3rem; height: 3rem" stroke-width="4" />
        <p class="m-0 font-semibold">{{ t('modificar.aplicando') }}</p>
        <p class="m-0 text-sm text-muted-color">{{ t('pago.noCierre') }}</p>
      </div>
    </Dialog>

    <DesafioBanco v-if="desafio" :url="desafio.url" :campos="desafio.campos" :ancho="desafio.ancho" :alto="desafio.alto" @completado="desafio.fin($event)" @cancelado="desafio.fin(null)" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave } from 'vue-router'
import * as api from '@/api'
import DesafioBanco from '@/componentes/DesafioBanco.vue'
import SelectorAsientoViaje from '@/componentes/modificar/SelectorAsientoViaje.vue'
import { mensajeDeError } from '@/errores'
import { REGION, type Idioma } from '@/i18n'
import { guardarLlave, leerLlave } from '@/llave'
import { diaISO, exigeCodigoPostal, expiraValida, fechaCorta, hora, luhn, marca, paises, quetzales, regionParaBanco } from '@/modelo'
import { pedidoDeModificacion, tomadosEnModificacion, type AsientoDestino } from '@/modificacion'
import { cargarHuella, datosNavegador, recolectarDispositivo } from '@/tresDs'
import type { BoletoViaje, CobroModificacion, CotizacionModificacion, PoliticaModificacion, Region, ResultadoModificacion, ViajeComprado } from '@/tipos'

const props = defineProps<{ viaje: number }>()
const { t, te, locale } = useI18n()
const region = computed(() => REGION[locale.value as Idioma])
const fechaHora = (iso: string) => `${fechaCorta(iso, region.value)}, ${hora(iso, region.value)}`

const cargando = ref(true)
const viaje = ref<ViajeComprado | null>(null)
const politica = ref<PoliticaModificacion | null>(null)
const error = ref('')
const errorDetalle = ref('')

/** Lo que el cliente va armando: a dónde pasa cada boleto y los asientos que agrega. */
const cambios = reactive<Record<number, AsientoDestino>>({})
const nuevos = ref<AsientoDestino[]>([])
const hayCambios = computed(() => Object.keys(cambios).length > 0 || nuevos.value.length > 0)
const tomados = computed(() => tomadosEnModificacion(Object.values(cambios), nuevos.value))

const cotizacion = ref<CotizacionModificacion | null>(null)
const cotizando = ref(false)
const confirmando = ref(false)
const ocupado = computed(() => cotizando.value || confirmando.value)
const resultado = ref<Extract<ResultadoModificacion, { estado: 'completado' }> | null>(null)

const tarjeta = reactive({ titular: '', numero: '', expira: '', cvv: '' })
const direccion = reactive({ pais: 'GT', regionId: null as number | null, region: '', ciudad: '', direccion: '', codigoPostal: '' })
const listaPaises = computed(() => paises(locale.value))
const usaCodigoPostal = computed(() => exigeCodigoPostal(direccion.pais))
const numeroValido = computed(() => luhn(tarjeta.numero) && marca(tarjeta.numero) !== null)
const expiraOk = computed(() => expiraValida(tarjeta.expira))
/** Se intentó pagar con campos sin llenar: los selects vacíos se marcan. */
const revisar = ref(false)

// Departamento → ciudad, como en la compra (ADR-041): vacías = el país o el departamento no tiene datos y se escribe a mano.
const regiones = ref<Region[]>([])
const ciudades = ref<Array<{ label: string; value: string }>>([])
const opcionesRegion = computed(() => regiones.value.map((r) => ({ label: r.nombre, value: r.id })))
let pedidoRegiones = 0
let pedidoCiudades = 0

watch(
  () => direccion.pais,
  async (pais) => {
    const pedido = ++pedidoRegiones
    regiones.value = []
    ciudades.value = []
    direccion.regionId = null
    direccion.region = ''
    direccion.ciudad = ''
    if (!pais) return
    const lista = await api.regiones(pais).catch(() => [])
    if (pedido === pedidoRegiones) regiones.value = lista
  },
  { immediate: true },
)

watch(
  () => direccion.regionId,
  async (id) => {
    const pedido = ++pedidoCiudades
    ciudades.value = []
    direccion.ciudad = ''
    if (id === null) return
    const lista = await api.ciudades(id).catch(() => [])
    if (pedido === pedidoCiudades) ciudades.value = lista.map((c) => ({ label: c, value: c }))
  },
)

/** Región para el banco: la de la lista (código en EE. UU. y Canadá) o la escrita a mano. */
function regionElegida(): string | undefined {
  const r = regiones.value.find((x) => x.id === direccion.regionId)
  if (r) return regionParaBanco(direccion.pais, r)
  return direccion.region.trim() || undefined
}

const restantes = (b: BoletoViaje) => Math.max(0, (politica.value?.modificacionesPorAsiento ?? 0) - b.modificaciones)

// Selector de salida y asiento (para un boleto que cambia o para un asiento nuevo).
type Edicion = { tipo: 'cambio'; boleto: BoletoViaje } | { tipo: 'nuevo' }
const selector = ref<Edicion | null>(null)
const selectorAbierto = ref(false)
const selectorClave = ref(0)
const tituloSelector = computed(() =>
  selector.value?.tipo === 'cambio' ? t('modificar.cambiarAsiento', { numero: selector.value.boleto.asiento }) : t('modificar.agregar'),
)
/** Punto de partida del selector: la ruta y el día del asiento que cambia (o los del viaje). */
const selectorOrigen = computed(() => {
  const b = selector.value?.tipo === 'cambio' ? selector.value.boleto : viaje.value!.boletos[0]!
  return { origen: b.trayecto.origen.id, destino: b.trayecto.destino.id, dia: diaISO(new Date(b.salida.salidaOrigen)) }
})

function abrir(e: Edicion) {
  selector.value = e
  selectorClave.value++
  selectorAbierto.value = true
}

function elegir(a: AsientoDestino) {
  const e = selector.value
  if (!e) return
  if (e.tipo === 'cambio') cambios[e.boleto.id] = a
  else nuevos.value = [...nuevos.value, a]
  selectorAbierto.value = false
  cotizacion.value = null
  error.value = ''
}

function deshacer(boleto: number) {
  delete cambios[boleto]
  cotizacion.value = null
}

function quitarNuevo(i: number) {
  nuevos.value = nuevos.value.filter((_, j) => j !== i)
  cotizacion.value = null
}

/** Desafío del banco en curso (como en el pago de la compra). */
const desafio = ref<{ url: string; campos: Record<string, string>; ancho: string; alto: string; fin: (d: Record<string, string> | null) => void } | null>(null)

function avisarSalida(e: BeforeUnloadEvent) {
  if (confirmando.value) e.preventDefault()
}
onBeforeUnmount(() => window.removeEventListener('beforeunload', avisarSalida))
onBeforeRouteLeave(() => !confirmando.value)

onMounted(async () => {
  window.addEventListener('beforeunload', avisarSalida)
  await cargar()
})

async function cargar() {
  const llave = leerLlave()
  try {
    if (!llave) return
    const r = await api.misCompras(llave)
    guardarLlave(r.llave)
    politica.value = r.politica
    const v = r.futuros.find((x) => x.id === props.viaje) ?? null
    if (v && !v.editable) error.value = v.motivo ?? ''
    viaje.value = v?.editable ? v : null
  } catch (e) {
    if (e instanceof api.ErrorPublico && e.estado === 401) guardarLlave(null)
    error.value = mensajeDeError(e, t, te).titulo
  } finally {
    cargando.value = false
  }
}

function fallo(e: unknown) {
  const m = e instanceof api.ErrorPublico ? mensajeDeError(e, t, te) : { titulo: e instanceof Error ? e.message : String(e), detalle: null }
  error.value = m.titulo
  errorDetalle.value = m.detalle ?? ''
}

async function cotizar() {
  const llave = leerLlave()
  if (!llave || !viaje.value) return
  cotizando.value = true
  error.value = ''
  errorDetalle.value = ''
  try {
    cotizacion.value = await api.cotizarModificacion(llave, viaje.value.id, pedidoDeModificacion(cambios, nuevos.value))
  } catch (e) {
    fallo(e)
  } finally {
    cotizando.value = false
  }
}

async function confirmar() {
  const llave = leerLlave()
  const c = cotizacion.value
  if (!llave || !viaje.value || !c) return
  const faltaDireccion = (regiones.value.length > 0 && direccion.regionId === null) || !direccion.ciudad.trim()
  if (c.requierePago && (!numeroValido.value || !expiraOk.value || !/^\d{3,4}$/.test(tarjeta.cvv.trim()) || faltaDireccion)) {
    revisar.value = true
    error.value = t('pago.revise')
    return
  }
  confirmando.value = true
  error.value = ''
  errorDetalle.value = ''
  const pedido = pedidoDeModificacion(cambios, nuevos.value)
  const id = viaje.value.id
  try {
    let cobro: CobroModificacion | undefined
    if (c.requierePago) {
      // Referencia del cobro: con ella el banco arma la huella del dispositivo antes de cobrar.
      const token = crypto.randomUUID()
      const h = await api.huellaModificacion(llave, id, token).catch(() => null)
      if (h?.script) cargarHuella(h.script)
      cobro = {
        token,
        tarjeta: { ...tarjeta },
        facturacion: {
          pais: direccion.pais,
          region: regionElegida(),
          ciudad: direccion.ciudad,
          direccion: direccion.direccion,
          codigoPostal: usaCodigoPostal.value && direccion.codigoPostal ? direccion.codigoPostal : undefined,
        },
        navegador: datosNavegador(),
      }
    }
    let r = await api.modificar(llave, id, pedido, cobro)
    // Pasos de 3-D Secure hasta que el banco apruebe (los rechazos llegan como error).
    for (let paso = 0; r.estado !== 'completado'; paso++) {
      if (paso > 4 || !cobro) throw new Error(t('pago.sinFin'))
      let continuar: Record<string, string> = {}
      if (r.estado === 'dispositivo') {
        await recolectarDispositivo(r.url, r.campos, r.origenes)
      } else {
        const respuesta = await desafiar(r.url, r.campos, r.ancho, r.alto)
        if (respuesta === null) throw new Error(t('pago.cancelado'))
        continuar = respuesta
      }
      r = await api.modificar(llave, id, pedido, { ...cobro, token: r.token, continuar })
    }
    resultado.value = r
    tarjeta.numero = tarjeta.cvv = tarjeta.expira = ''
  } catch (e) {
    fallo(e)
    // Lo cotizado pudo cambiar (precio, asiento tomado): se revisa de nuevo.
    if (e instanceof api.ErrorPublico && ['asientos_no_disponibles', 'precio_cambiado', 'reembolsado', 'modificacion_cerrada'].includes(e.codigo)) cotizacion.value = null
  } finally {
    desafio.value = null
    confirmando.value = false
  }
}

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
