/**
 * Secciones de "Mi cuenta", en el orden del menú. Una opción nueva que el
 * usuario edite de sí mismo va en la sección de su tema o, si no encaja en
 * ninguna, en una nueva aquí (con su componente en `MiCuentaPage`).
 */
export const SECCIONES = [
  { id: 'perfil', titulo: 'Perfil', icono: 'person-outline', descripcion: 'Su foto y sus datos de contacto.' },
  { id: 'seguridad', titulo: 'Seguridad', icono: 'lock-outline', descripcion: 'La contraseña con la que entra.' },
  { id: 'notificaciones', titulo: 'Notificaciones', icono: 'notifications-outline', descripcion: 'Los avisos del chat y los reportes que recibe.' },
  { id: 'apariencia', titulo: 'Apariencia', icono: 'palette-outline', descripcion: 'Modo claro u oscuro y colores. Se guarda en este navegador.' },
] as const

export type SeccionId = (typeof SECCIONES)[number]['id']

/** La sección de `?seccion=`; la primera si falta o no existe. */
export function seccionDe(valor: unknown): SeccionId {
  return SECCIONES.find((s) => s.id === valor)?.id ?? SECCIONES[0].id
}

export const mensajeDeError = (e: unknown, defecto: string) => (e instanceof Error && e.message ? e.message : defecto)
