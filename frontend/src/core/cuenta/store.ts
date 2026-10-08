/**
 * La cuenta del usuario con sesión (nombre, foto…): la usan el encabezado y
 * la pantalla "Mi cuenta". Se carga al entrar al shell y se limpia al salir.
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { useSessionStore } from '@/core/auth/session'
import * as api from './api'
import type { Cuenta, DatosCuenta } from './api'

export const useCuentaStore = defineStore('cuenta', () => {
  const cuenta = ref<Cuenta | null>(null)

  const foto = computed(() => api.urlFoto(cuenta.value?.foto))
  /** Nombre para mostrar: "Nombre Apellido" y, sin él, el usuario. */
  const nombre = computed(
    () =>
      [cuenta.value?.nombre, cuenta.value?.apellido].filter(Boolean).join(' ') ||
      useSessionStore().user ||
      '',
  )

  async function cargar() {
    try {
      cuenta.value = await api.fetchCuenta()
    } catch {
      // El encabezado funciona con el usuario de la sesión; "Mi cuenta" reintenta.
    }
  }

  async function guardar(datos: DatosCuenta) {
    cuenta.value = await api.guardarCuenta(datos)
  }

  async function subirFoto(imagen: Blob, nombreArchivo: string) {
    cuenta.value = await api.subirFoto(imagen, nombreArchivo)
  }

  async function quitarFoto() {
    cuenta.value = await api.quitarFoto()
  }

  function limpiar() {
    cuenta.value = null
  }

  return { cuenta, foto, nombre, cargar, guardar, subirFoto, quitarFoto, limpiar }
})
