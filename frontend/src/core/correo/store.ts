/**
 * Estado del correo de la sesión (ADR-048): la cuenta y sus contadores, la
 * lista de la vista con sus filtros y el correo abierto. Se entera de lo
 * nuevo por el mismo tópico privado del chat (aviso `correo`, que reenvía
 * el store del chat); sin él, refresca cada `RESPALDO_MS`.
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import * as api from './api'
import { SIN_FILTROS, aplicarLocal } from './modelo'
import type { Accion, AvisoCorreo, Estado, Filtros, Resumen, Vista } from './types'

const RESPALDO_MS = 60_000

export const useCorreoStore = defineStore('correo', () => {
  const estado = ref<Estado | null>(null)
  const vista = ref<Vista>('entrada')
  const filtros = ref<Filtros>({ ...SIN_FILTROS })
  const correos = ref<Resumen[]>([])
  const mas = ref(false)
  const cargando = ref(false)
  const listo = ref(false)
  /** Último correo que llegó (lo muestra el aviso flotante del shell). */
  const entrante = ref<{ id: number; de: string; asunto: string; n: number } | null>(null)
  let avisos = 0
  let respaldo: ReturnType<typeof setInterval> | null = null
  /** Descarta respuestas viejas si se cambió de vista o de filtros mientras tanto. */
  let peticion = 0

  const noLeidos = computed(() => estado.value?.carpetas.entrada.noLeidos ?? 0)

  async function iniciar() {
    if (respaldo) return
    await refrescarEstado(true)
    respaldo = setInterval(() => void refrescarEstado(true), RESPALDO_MS)
  }

  function detener() {
    if (respaldo) clearInterval(respaldo)
    respaldo = null
    estado.value = null
    correos.value = []
    listo.value = false
    entrante.value = null
    vista.value = 'entrada'
    filtros.value = { ...SIN_FILTROS }
  }

  async function refrescarEstado(silencioso = false) {
    const e = await api.fetchEstado(silencioso).catch(() => null)
    if (e) estado.value = e
  }

  async function cargar(silencioso = false) {
    const n = ++peticion
    if (!silencioso) cargando.value = true
    try {
      const r = await api.fetchCorreos(vista.value, filtros.value, undefined, silencioso)
      if (n !== peticion) return
      correos.value = r.correos
      mas.value = r.mas
      listo.value = true
    } finally {
      if (n === peticion) cargando.value = false
    }
  }

  async function cargarMas() {
    const ultimo = correos.value.at(-1)
    if (!mas.value || !ultimo || cargando.value) return
    const n = peticion
    cargando.value = true
    try {
      const r = await api.fetchCorreos(vista.value, filtros.value, ultimo.id, true)
      if (n !== peticion) return
      const ya = new Set(correos.value.map((c) => c.id))
      correos.value = [...correos.value, ...r.correos.filter((c) => !ya.has(c.id))]
      mas.value = r.mas
    } finally {
      cargando.value = false
    }
  }

  function ver(v: Vista) {
    if (vista.value === v) return void cargar()
    vista.value = v
    correos.value = []
    listo.value = false
    void cargar()
  }

  function filtrar(cambio: Partial<Filtros>) {
    filtros.value = { ...filtros.value, ...cambio }
    void cargar()
  }

  /** Aplica en la lista al instante y luego en el servidor; si falla, vuelve a pedir la lista. */
  async function aplicar(ids: number[], accion: Accion) {
    if (!ids.length) return
    correos.value = aplicarLocal(correos.value, ids, accion, vista.value, filtros.value)
    try {
      await api.aplicar(ids, accion)
    } catch (e) {
      void cargar(true)
      throw e
    } finally {
      void refrescarEstado(true)
    }
  }

  /** Lo leído en el lector: la fila y el contador al día sin pedir la lista. */
  function marcarLeidoLocal(id: number) {
    const fila = correos.value.find((c) => c.id === id)
    if (!fila || fila.leido) return
    correos.value = aplicarLocal(correos.value, [id], 'leido', vista.value, { ...filtros.value, noLeidos: false })
    if (estado.value && fila.carpeta === 'entrada') estado.value.carpetas.entrada.noLeidos = Math.max(0, estado.value.carpetas.entrada.noLeidos - 1)
  }

  async function vaciarPapelera() {
    await api.vaciarPapelera()
    if (vista.value === 'papelera') correos.value = []
    void refrescarEstado(true)
  }

  /** Algo cambió en el buzón (otra pestaña, un correo nuevo, una entrega). */
  function alAvisar(aviso: AvisoCorreo) {
    void refrescarEstado(true)
    if (listo.value) void cargar(true)
    if (aviso.nuevo) entrante.value = { id: aviso.nuevo, de: aviso.de ?? '', asunto: aviso.asunto ?? '', n: ++avisos }
  }

  return { estado, vista, filtros, correos, mas, cargando, listo, entrante, noLeidos, iniciar, detener, refrescarEstado, cargar, cargarMas, ver, filtrar, aplicar, marcarLeidoLocal, vaciarPapelera, alAvisar }
})
