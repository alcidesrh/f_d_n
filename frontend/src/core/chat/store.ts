/**
 * Estado del chat de la sesión: bandeja, mensajes por canal, conversación
 * abierta y avisos en tiempo real (tópico privado de Mercure). Sin Mercure
 * la bandeja se refresca cada `RESPALDO_MS`; la conversación abierta, igual.
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { suscribir } from '@/core/realtime'
import * as api from './api'
import { aplicarAviso, aplicarPresencia, totalNoLeidos, ultimo, unir } from './modelo'
import type { Aviso, Canal, Destinos, Mensaje, Perfil, Recurso, Referencia } from './types'


const RESPALDO_MS = 30_000
const REINTENTO_MS = 15_000
/** Cada cuánto se avisa que seguimos aquí (y se pregunta quién está en línea). */
const LATIDO_MS = 30_000

export const useChatStore = defineStore('chat', () => {
  const yo = ref<Perfil | null>(null)
  const canales = ref<Canal[]>([])
  const mensajes = ref<Record<number, Mensaje[]>>({})
  /** Canales sin mensajes más antiguos por cargar. */
  const completos = ref<Record<number, boolean>>({})
  const contactos = ref<Perfil[] | null>(null)
  const recursos = ref<Recurso[] | null>(null)
  /** Conversación en pantalla (la marca leída al llegar mensajes). */
  const activo = ref<number | null>(null)
  const listo = ref(false)
  const enVivo = ref(false)
  /** Usuarios con la aplicación abierta, de los que conozco (se actualiza con cada latido). */
  const enLinea = ref<number[]>([])
  /** Último mensaje ajeno fuera de la conversación en pantalla (lo muestra el shell). */
  const entrante = ref<{ canal: number; autor: Perfil | null; extracto: string; n: number } | null>(null)

  const noLeidos = computed(() => totalNoLeidos(canales.value))
  const canal = (id: number) => canales.value.find((c) => c.id === id) ?? null

  const estaEnLinea = (usuario: number) => enLinea.value.includes(usuario)
  /** Los usuarios cuya presencia se muestra: contactos, miembros de mis canales y la lista de contactos si ya se cargó. */
  const conocidos = () => {
    const ids = new Set<number>(contactos.value?.map((p) => p.id))
    for (const c of canales.value) {
      if (c.contacto) ids.add(c.contacto.id)
      for (const m of c.miembros) ids.add(m.id)
    }
    ids.delete(yo.value?.id ?? 0)
    return [...ids]
  }

  /** Identifica esta pestaña: el usuario sigue en línea mientras alguna siga viva. */
  const conexion = typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`

  async function latir() {
    const r = await api.latir(conexion, conocidos()).catch(() => null)
    if (r) enLinea.value = r.enLinea
  }

  let latido: ReturnType<typeof setInterval> | null = null
  let desuscribir: (() => void) | null = null
  let respaldo: ReturnType<typeof setInterval> | null = null
  let reintento: ReturnType<typeof setTimeout> | null = null
  let avisos = 0

  const visible = () => typeof document === 'undefined' || document.visibilityState === 'visible'
  const leyendo = () => (visible() ? activo.value : null)
  /** Al volver a la pestaña, lo que llegó a la conversación abierta queda leído. */
  const alVolver = () => {
    if (!visible()) return
    void latir()
    if (activo.value) void leer(activo.value)
  }

  /** Se cierra la pestaña: se avisa antes de que el navegador la corte. */
  const alIrse = () => void api.salirDeLinea(conexion)

  async function iniciar() {
    if (respaldo) return
    document.addEventListener('visibilitychange', alVolver)
    window.addEventListener('pagehide', alIrse)
    await refrescar()
    void conectar()
    void latir()
    latido = setInterval(() => void latir(), LATIDO_MS)
    respaldo = setInterval(() => {
      if (enVivo.value) return
      void refrescar(true)
      if (activo.value) void traerNuevos(activo.value)
    }, RESPALDO_MS)
  }

  /**
   * Deja de figurar en línea. Al cerrar sesión hay que esperarlo antes del
   * logout: después el token ya está revocado y el aviso no pasaría.
   */
  async function salirDeLinea() {
    if (!latido) return
    clearInterval(latido)
    latido = null
    if (yo.value) await api.salirDeLinea(conexion)
  }

  function detener() {
    void salirDeLinea()
    desuscribir?.()
    desuscribir = null
    document.removeEventListener('visibilitychange', alVolver)
    window.removeEventListener('pagehide', alIrse)
    if (respaldo) clearInterval(respaldo)
    if (reintento) clearTimeout(reintento)
    respaldo = reintento = null
    yo.value = null
    canales.value = []
    mensajes.value = {}
    completos.value = {}
    contactos.value = null
    recursos.value = null
    activo.value = null
    entrante.value = null
    enLinea.value = []
    listo.value = enVivo.value = false
  }

  async function conectar() {
    desuscribir?.()
    try {
      const { token, topico } = await api.fetchToken()
      desuscribir = suscribir(topico, (d) => void recibir(d as Aviso), {
        token,
        alCerrarse: () => {
          enVivo.value = false
          reintento = setTimeout(() => void conectar(), REINTENTO_MS)
        },
      })
      enVivo.value = true
      // Pudo perderse algún cambio mientras estaba cortado.
      void latir()
    } catch {
      enVivo.value = false
    }
  }

  async function refrescar(silencioso = false) {
    const bandeja = await api.fetchBandeja(silencioso).catch(() => null)
    if (!bandeja) return
    yo.value = bandeja.yo
    canales.value = bandeja.canales
    listo.value = true
  }

  async function recibir(aviso: Aviso) {
    if (aviso.tipo === 'presencia') {
      enLinea.value = aplicarPresencia(enLinea.value, aviso.usuario, aviso.enLinea)
      return
    }
    const despues = aplicarAviso(canales.value, aviso, yo.value?.id ?? null, leyendo())
    if (despues) canales.value = despues
    else await refrescar(true)
    if (aviso.tipo !== 'mensaje') return
    if (mensajes.value[aviso.canal]) await traerNuevos(aviso.canal)
    if (aviso.autor?.id === yo.value?.id) return
    if (aviso.canal === leyendo()) void leer(aviso.canal)
    else entrante.value = { canal: aviso.canal, autor: aviso.autor, extracto: aviso.extracto, n: ++avisos }
  }

  async function traerNuevos(id: number) {
    const actuales = mensajes.value[id] ?? []
    const nuevos = await api.fetchMensajes(id, { despues: ultimo(actuales)?.id }).catch(() => [])
    if (nuevos.length) mensajes.value[id] = unir(mensajes.value[id] ?? [], nuevos)
  }

  /** Trae los últimos mensajes del canal (o los nuevos, si ya los tenía) sin marcarlo leído. */
  async function cargar(id: number) {
    if (!canal(id)) await refrescar(true)
    if (!mensajes.value[id]) {
      const primeros = await api.fetchMensajes(id)
      mensajes.value[id] = primeros
      completos.value[id] = primeros.length < 40
    } else {
      await traerNuevos(id)
    }
  }

  /** Pone el canal en pantalla, trae sus últimos mensajes y lo marca leído. */
  async function abrir(id: number) {
    activo.value = id
    await cargar(id)
    if (entrante.value?.canal === id) entrante.value = null
    await leer(id)
  }

  function cerrar(id: number) {
    if (activo.value === id) activo.value = null
  }

  async function cargarAnteriores(id: number) {
    const primero = mensajes.value[id]?.[0]
    if (!primero || completos.value[id]) return
    const previos = await api.fetchMensajes(id, { antes: primero.id })
    completos.value[id] = previos.length < 40
    mensajes.value[id] = unir(previos, mensajes.value[id] ?? [])
  }

  async function leer(id: number) {
    const hasta = ultimo(mensajes.value[id] ?? [])?.id ?? canal(id)?.ultimo?.id
    const c = canal(id)
    if (!hasta || !c || (c.noLeidos === 0 && c.leidoHasta >= hasta)) return
    c.noLeidos = 0
    c.leidoHasta = hasta
    await api.marcarLeido(id, hasta).catch(() => undefined)
  }

  async function enviar(id: number, envio: api.Envio) {
    const m = await api.escribir(id, envio)
    mensajes.value[id] = unir(mensajes.value[id] ?? [], [m])
    const c = canal(id)
    if (c) {
      const actualizado = { ...c, actividad: m.fecha, leidoHasta: m.id, ultimo: { id: m.id, autor: m.autor?.id ?? null, extracto: m.texto || (m.archivos.length ? (m.archivos.every((a) => a.imagen) ? 'Foto' : 'Archivo') : 'Compartió registros'), fecha: m.fecha } }
      canales.value = [actualizado, ...canales.value.filter((x) => x.id !== id)]
    }
    return m
  }

  /** Tipos que el usuario puede adjuntar; una vez por sesión. */
  async function cargarRecursos() {
    recursos.value ??= await api.fetchRecursos()
    return recursos.value
  }

  async function cargarContactos() {
    contactos.value ??= await api.fetchContactos()
    return contactos.value
  }

  /** Conversación directa con un usuario (la crea si no existe); devuelve su id. */
  async function directo(usuario: number) {
    const existente = canales.value.find((c) => c.tipo === 'directo' && c.contacto?.id === usuario)
    if (existente) return existente.id
    const nuevo = await api.abrirDirecto(usuario)
    canales.value = [nuevo, ...canales.value]
    return nuevo.id
  }

  async function grupo(nombre: string, miembros: number[]) {
    const nuevo = await api.crearGrupo(nombre, miembros)
    canales.value = [nuevo, ...canales.value]
    return nuevo.id
  }

  async function compartir(destinos: Destinos, adjuntos: Referencia[], texto = '') {
    const r = await api.compartir(destinos, adjuntos, texto)
    await refrescar(true)
    for (const id of r.canales) if (mensajes.value[id]) await traerNuevos(id)
    return r.canales
  }

  return {
    yo, canales, mensajes, completos, contactos, recursos, activo, listo, enVivo, enLinea, entrante, noLeidos, estaEnLinea,
    canal, iniciar, detener, salirDeLinea, refrescar, cargar, abrir, cerrar, cargarAnteriores, leer, enviar, cargarContactos, cargarRecursos, directo, grupo, compartir,
  }
})
