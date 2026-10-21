/**
 * Rutas de la aplicación (vue-router) que un enlace de la administración
 * puede abrir: con nombre, privadas y que no son la de "no encontrada".
 */
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { Destino } from '@/core/administracion/api'
import { destinoValido, parametrosDeRuta } from '@/core/administracion/disposicion'

export interface RutaApp {
  name: string
  path: string
  label: string
}

export function useRutasApp() {
  const router = useRouter()

  const rutas = computed<RutaApp[]>(() =>
    router
      .getRoutes()
      .filter((r) => typeof r.name === 'string' && !r.meta.public && r.name !== 'not-found')
      .map((r) => ({ name: r.name as string, path: r.path, label: String(r.meta.title ?? r.meta.label ?? r.name) }))
      .sort((a, b) => a.label.localeCompare(b.label)),
  )

  const parametrosDe = (nombre: string | null | undefined) => parametrosDeRuta(rutas.value.find((r) => r.name === nombre)?.path ?? '')

  const existe = (nombre: string) => router.hasRoute(nombre)

  /** Se puede guardar: ruta existente con sus parámetros obligatorios, o un path válido. */
  const valido = (d: Destino | null) =>
    !!d && (d.ruta ? existe(d.ruta) && destinoValido(d, parametrosDe(d.ruta).filter((p) => !p.opcional).map((p) => p.nombre)) : destinoValido(d))

  return { rutas, parametrosDe, existe, valido }
}
