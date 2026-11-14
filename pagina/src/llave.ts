/**
 * Llave de «Mis compras»: el acceso sin contraseña del cliente a sus compras
 * (la firma el backend, atada a su correo). Queda en `localStorage` al
 * terminar una compra o al abrir el enlace del correo del boleto: ese
 * navegador entra a sus compras sin que el cliente haga nada.
 */
const CLAVE = 'fdn.llave'

export function leerLlave(): string | null {
  try {
    return localStorage.getItem(CLAVE)
  } catch {
    return null
  }
}

export function guardarLlave(llave: string | null | undefined) {
  try {
    if (llave) localStorage.setItem(CLAVE, llave)
    else localStorage.removeItem(CLAVE)
  } catch {
    // Sin almacenamiento, la llave dura lo que la pestaña: se pide otra por correo.
  }
}
