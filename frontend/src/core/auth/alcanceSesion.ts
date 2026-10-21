/**
 * Alcance de las stores: todo es del usuario con sesión (venta a medias,
 * permisos, filtros y columnas de los listados, sidebars, tema…) salvo las
 * stores marcadas `global: true` (sesión, schema, carga en curso).
 *
 * - Lo persistido va en claves propias de cada usuario (`usuario/{nombre}/{clave}`,
 *   `almacenPorUsuario`): cada uno reencuentra sus preferencias y nunca ve las
 *   de otro. Lo guardado a mano en localStorage usa `claveDeUsuario`.
 * - Al entrar otro usuario las instancias en memoria se descartan y el próximo
 *   `useX()` las crea de nuevo con lo de él. Las `permanente` (las usa la app
 *   entera, p. ej. el tema en `App.vue`) no se pueden descartar: se recargan en
 *   el sitio y luego se llama a su `alRecargar`. Quien guarde instancias fuera
 *   de Pinia (el registro de entidades) las suelta con `alDescartar`.
 *
 * El dueño se guarda en localStorage: si la sesión vence (401) y vuelve el
 * mismo usuario, conserva su trabajo; si entra otro, se descarta.
 */
import type { PiniaPluginContext, StateTree, Store } from 'pinia'
import type { StorageLike } from 'pinia-plugin-persistedstate'

declare module 'pinia' {
  export interface DefineStoreOptionsBase<S extends StateTree, Store> {
    /** No es del usuario: compartida por todos los que usan el navegador. */
    global?: boolean
    /** La instancia vive toda la app: al cambiar de usuario se recarga en el sitio (requiere `$reset`). */
    permanente?: { alRecargar?: () => void }
  }
}

const PROPIETARIO = 'sesion:propietario'

/** Claves persistidas de las stores globales: no llevan usuario. */
const clavesGlobales = new Set<string>()
const descartables = new Map<string, Store>()
const permanentes = new Map<string, { store: Store; alRecargar?: () => void }>()
const avisosAlDescartar = new Set<() => void>()
/** Recargando una permanente: su `$reset` no debe pisar lo guardado del usuario. */
let silencio = false

const propietario = (): string | null => localStorage.getItem(PROPIETARIO)

/** Clave de localStorage del usuario con sesión (o del navegador sin sesión). */
export function claveDeUsuario(clave: string): string {
  const usuario = propietario()
  return usuario ? `usuario/${usuario}/${clave}` : `sin-usuario/${clave}`
}

const claveReal = (clave: string) => (clavesGlobales.has(clave) ? clave : claveDeUsuario(clave))

/** Storage de `pinia-plugin-persistedstate`: separa lo de cada usuario. */
export const almacenPorUsuario: StorageLike = {
  getItem: (clave) => localStorage.getItem(claveReal(clave)),
  setItem: (clave, valor) => {
    if (!silencio) localStorage.setItem(claveReal(clave), valor)
  },
}

function clavesPersistidas(id: string, persist: unknown): string[] {
  if (!persist) return []
  return (Array.isArray(persist) ? persist : [persist]).map((p) =>
    typeof p === 'object' && p && 'key' in p && typeof p.key === 'string' ? p.key : id,
  )
}

/** Plugin de Pinia (registrarlo antes que el de persistencia): clasifica cada store. */
export function alcanceSesion({ store, options, pinia }: PiniaPluginContext): void {
  const claves = clavesPersistidas(store.$id, (options as { persist?: unknown }).persist)
  if (options.global) {
    claves.forEach((c) => clavesGlobales.add(c))
    return
  }
  // Copia de antes de separar por usuario: ya no se lee.
  claves.forEach((c) => localStorage.removeItem(c))

  if (options.permanente) {
    permanentes.set(store.$id, { store, alRecargar: options.permanente.alRecargar })
    return
  }
  descartables.set(store.$id, store)
  const dispose = store.$dispose.bind(store)
  store.$dispose = () => {
    descartables.delete(store.$id)
    dispose()
    // Sin su estado, el próximo `useX()` corre el setup/state de nuevo.
    delete pinia.state.value[store.$id]
  }
}

/**
 * `aviso` corre cada vez que se descartan las stores del usuario: quien guarda
 * instancias fuera de Pinia las suelta ahí (Pinia ya no las tiene ni las persiste).
 */
export function alDescartar(aviso: () => void): void {
  avisosAlDescartar.add(aviso)
}

/** Descarta de memoria las stores del usuario (lo persistido queda en sus claves). */
export function descartarStoresDeUsuario(): void {
  for (const store of [...descartables.values()]) store.$dispose()
  avisosAlDescartar.forEach((aviso) => aviso())
}

/** Entra `usuario`: si las stores eran de otro (o de nadie), toma las suyas. */
export function entrarComo(usuario: string): void {
  if (propietario() === usuario) return
  localStorage.setItem(PROPIETARIO, usuario)
  descartarStoresDeUsuario()
  for (const { store, alRecargar } of permanentes.values()) {
    silencio = true
    try {
      store.$reset()
    } finally {
      silencio = false
    }
    ;(store as Store & { $hydrate?: () => void }).$hydrate?.()
    alRecargar?.()
  }
}

/**
 * Cierre de sesión explícito: la venta a medias y demás trabajo en memoria
 * se descartan. Llamarlo ya fuera de las pantallas del usuario (en el login).
 * El dueño se queda: el login se ve como lo dejó el último usuario.
 */
export function salirDeStores(): void {
  descartarStoresDeUsuario()
}
