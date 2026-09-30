/**
 * Estado de la pantalla de venta (taquilla y agencias, ADR-021): filtros,
 * salida elegido, tramo, ocupación en vivo (Mercure), selección de
 * asientos, cliente y cotización. La lógica pura está en `core/venta/modelo`.
 */
import { defineStore } from 'pinia'
import { computed, ref, shallowRef } from 'vue'
import * as api from '@/core/venta/api'
import {
  alternar,
  bajadaPorDefecto,
  depurarSeleccion,
  diaISO,
  estadoEnMapa,
  nuevoToken,
  paradasDeBajada,
  paradasDeSubida,
  subidaPorDefecto,
  trayectoEntre,
} from '@/core/venta/modelo'
import { suscribir } from '@/core/realtime'
import { notify } from '@/core/notify'
import type {
  AsientoOcupado,
  Cliente,
  Comprobante,
  ContextoVenta,
  Cotizacion,
  ErrorVenta,
  SalidaDetalle,
  SalidaResumen,
} from '@/core/venta/types'

export interface OpcionesCobro {
  tipoPago: number | null
  moneda: number | null
  enviarCorreo: boolean
  cortesia: boolean
  sinFacturaElectronica?: boolean
}

export const useVentaStore = defineStore('venta', () => {
  const contexto = shallowRef<ContextoVenta | null>(null)
  const errorCarga = ref('')

  const fecha = ref(new Date())
  const estacionId = ref<number | null>(null)
  const salidas = ref<SalidaResumen[]>([])
  const cargandoSalidas = ref(false)

  const salidaId = ref<number | null>(null)
  const detalle = shallowRef<SalidaDetalle | null>(null)
  const cargandoDetalle = ref(false)
  const sube = ref<number | null>(null)
  const baja = ref<number | null>(null)
  const ocupados = ref<AsientoOcupado[]>([])

  const seleccion = ref<number[]>([])
  const cliente = ref<Cliente | null>(null)
  /** Pasajero por asiento (si no, viaja el cliente de la venta). */
  const pasajeros = ref<Record<number, Cliente | null>>({})
  const observacion = ref('')
  const cobrarTrayectoCompleto = ref(false)
  const cotizacion = shallowRef<Cotizacion | null>(null)
  const errorCotizacion = ref('')

  const vendiendo = ref(false)
  /** Clave de idempotencia de la venta en curso (se renueva al terminarla). */
  const token = ref(nuevoToken())
  const falloFacturacion = ref<ErrorVenta | null>(null)
  const comprobante = shallowRef<Comprobante | null>(null)

  let desuscribir: (() => void) | null = null

  const trayectoId = computed(() =>
    detalle.value ? trayectoEntre(detalle.value, sube.value, baja.value) : null,
  )
  const esSubtrayecto = computed(
    () => !!detalle.value && trayectoId.value !== detalle.value.trayecto.id,
  )
  const subidas = computed(() => (detalle.value ? paradasDeSubida(detalle.value) : []))
  const bajadas = computed(() => (detalle.value ? paradasDeBajada(detalle.value, sube.value) : []))
  const estadoAsiento = computed(() => estadoEnMapa(ocupados.value, seleccion.value))
  const asientosCroquis = computed(
    () => detalle.value?.croquis.filter((e) => e.tipo === 'asiento') ?? [],
  )
  const libres = computed(
    () => asientosCroquis.value.length - ocupados.value.filter((o) => o.estado !== 'propio').length,
  )
  const puedeVender = computed(
    () =>
      !!cliente.value &&
      !!detalle.value &&
      trayectoId.value != null &&
      seleccion.value.length > 0 &&
      !vendiendo.value,
  )

  async function iniciar() {
    errorCarga.value = ''
    try {
      contexto.value = await api.fetchContexto()
      estacionId.value ??= contexto.value.estacion?.id ?? null
      await cargarSalidas()
    } catch (e) {
      errorCarga.value = e instanceof Error ? e.message : String(e)
    }
  }

  async function refrescarContexto() {
    try {
      contexto.value = await api.fetchContexto()
    } catch {
      // El saldo de la agencia se verá desactualizado hasta el próximo refresco.
    }
  }

  async function cargarSalidas() {
    cargandoSalidas.value = true
    try {
      salidas.value = await api.fetchSalidas(diaISO(fecha.value), estacionId.value)
      if (salidaId.value && !salidas.value.some((r) => r.id === salidaId.value)) {
        cerrarSalida()
      }
    } catch (e) {
      salidas.value = []
      notify.error(api.errorVenta(e)?.error ?? 'No se pudieron cargar los salidas.')
    } finally {
      cargandoSalidas.value = false
    }
  }

  async function elegirSalida(id: number) {
    if (id === salidaId.value && detalle.value) return
    cerrarSalida()
    salidaId.value = id
    cargandoDetalle.value = true
    try {
      const d = await api.fetchSalida(id)
      if (salidaId.value !== id) return
      detalle.value = d
      sube.value = subidaPorDefecto(d, estacionId.value)
      baja.value = bajadaPorDefecto(d, sube.value)
      await cargarOcupacion()
      desuscribir = suscribir(d.topico, () => void refrescarOcupacion())
    } catch (e) {
      notify.error(api.errorVenta(e)?.error ?? 'No se pudo abrir el salida.')
      cerrarSalida()
    } finally {
      cargandoDetalle.value = false
    }
  }

  function cerrarSalida() {
    desuscribir?.()
    desuscribir = null
    salidaId.value = null
    detalle.value = null
    ocupados.value = []
    seleccion.value = []
    pasajeros.value = {}
    cotizacion.value = null
    cobrarTrayectoCompleto.value = false
  }

  async function cargarOcupacion(silenciosa = false) {
    if (!salidaId.value || trayectoId.value == null) return
    ocupados.value = await api.fetchOcupacion(salidaId.value, trayectoId.value, silenciosa)
  }

  /** Refresco en vivo: si otro vendió un asiento elegido, se avisa y se quita. */
  async function refrescarOcupacion() {
    try {
      await cargarOcupacion(true)
    } catch {
      return
    }
    const depurada = depurarSeleccion(seleccion.value, ocupados.value)
    if (depurada.length !== seleccion.value.length) {
      notify.warning('Otro vendedor tomó un asiento que tenía elegido: se quitó de la selección.')
      seleccion.value = depurada
      void recotizar()
    }
  }

  async function cambiarSubida(id: number | null) {
    sube.value = id
    if (detalle.value && !bajadas.value.some((p) => p.id === baja.value)) {
      baja.value = bajadaPorDefecto(detalle.value, id)
    }
    await cambioDeTramo()
  }

  async function cambiarBajada(id: number | null) {
    baja.value = id
    await cambioDeTramo()
  }

  async function cambioDeTramo() {
    if (!esSubtrayecto.value) cobrarTrayectoCompleto.value = false
    await cargarOcupacion()
    seleccion.value = depurarSeleccion(seleccion.value, ocupados.value)
    await recotizar()
  }

  function alternarAsiento(id: number) {
    seleccion.value = alternar(seleccion.value, id)
    if (!seleccion.value.includes(id)) delete pasajeros.value[id]
    void recotizar()
  }

  let cotizacionEnCurso = 0
  async function recotizar(cortesia = false) {
    const n = ++cotizacionEnCurso
    errorCotizacion.value = ''
    if (!salidaId.value || !seleccion.value.length) {
      cotizacion.value = null
      return
    }
    try {
      const c = await api.cotizar({
        salida: salidaId.value,
        trayecto: trayectoId.value,
        asientos: seleccion.value,
        cobrarTrayectoCompleto: cobrarTrayectoCompleto.value,
        cortesia,
      })
      if (n === cotizacionEnCurso) cotizacion.value = c
    } catch (e) {
      if (n !== cotizacionEnCurso) return
      cotizacion.value = null
      errorCotizacion.value = api.errorVenta(e)?.error ?? 'No se pudo calcular la tarifa.'
    }
  }

  /**
   * Registra la venta. Devuelve el comprobante; si el certificador falló,
   * deja el motivo en `falloFacturacion` (la venta no quedó registrada).
   */
  async function vender(opciones: OpcionesCobro): Promise<Comprobante | null> {
    if (!puedeVender.value || !salidaId.value || !cliente.value) return null
    vendiendo.value = true
    falloFacturacion.value = null
    try {
      const c = await api.vender({
        token: token.value,
        salida: salidaId.value,
        trayecto: trayectoId.value,
        asientos: seleccion.value.map((asiento) => ({
          asiento,
          cliente: pasajeros.value[asiento]?.id ?? null,
        })),
        cliente: cliente.value.id,
        estacion: contexto.value?.canal === 'estacion' ? estacionId.value : null,
        observacion: observacion.value.trim() || null,
        cobrarTrayectoCompleto: cobrarTrayectoCompleto.value,
        tipoPago: opciones.tipoPago,
        moneda: opciones.moneda,
        enviarCorreo: opciones.enviarCorreo,
        cortesia: opciones.cortesia,
        sinFacturaElectronica: opciones.sinFacturaElectronica ?? false,
      })
      comprobante.value = c
      terminarVenta()
      if (contexto.value?.canal === 'agencia') void refrescarContexto()
      void refrescarOcupacion()
      void cargarSalidas()
      return c
    } catch (e) {
      const err = api.errorVenta(e)
      if (err?.codigo === 'facturacion') {
        falloFacturacion.value = err
      } else {
        notify.error(err?.error ?? 'No se pudo registrar la venta.')
        if (err?.codigo === 'asientos_no_disponibles') await refrescarOcupacion()
        if (err?.codigo === 'saldo_insuficiente') void refrescarContexto()
      }
      return null
    } finally {
      vendiendo.value = false
    }
  }

  /** Deja lista la pantalla para la siguiente venta (mismo salida y cliente). */
  function terminarVenta() {
    token.value = nuevoToken()
    seleccion.value = []
    pasajeros.value = {}
    observacion.value = ''
    cotizacion.value = null
    falloFacturacion.value = null
  }

  function cancelarVenta() {
    falloFacturacion.value = null
    token.value = nuevoToken()
  }

  return {
    contexto,
    errorCarga,
    fecha,
    estacionId,
    salidas,
    cargandoSalidas,
    salidaId,
    detalle,
    cargandoDetalle,
    sube,
    baja,
    ocupados,
    seleccion,
    cliente,
    pasajeros,
    observacion,
    cobrarTrayectoCompleto,
    cotizacion,
    errorCotizacion,
    vendiendo,
    falloFacturacion,
    comprobante,
    trayectoId,
    esSubtrayecto,
    subidas,
    bajadas,
    estadoAsiento,
    asientosCroquis,
    libres,
    puedeVender,
    iniciar,
    cargarSalidas,
    elegirSalida,
    cerrarSalida,
    cambiarSubida,
    cambiarBajada,
    alternarAsiento,
    recotizar,
    vender,
    terminarVenta,
    cancelarVenta,
  }
})
