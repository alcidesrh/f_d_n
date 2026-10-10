/**
 * Lo que la bitácora guarda en `detalle`, dicho en una línea para quien la
 * lee. Espejo de lo que escribe `App\Bitacora\RegistradorBitacora`.
 */
import type { EntradaBitacora, TipoBitacora } from './types'

const texto = (v: unknown): string | null => (v === null || v === undefined || v === '' ? null : String(v))

const ESTADOS: Record<string, string> = {
  programada: 'programada',
  abordando: 'abordando',
  iniciada: 'iniciada',
  finalizada: 'finalizada',
  cancelada: 'cancelada',
}

const CANALES: Record<string, string> = { estacion: 'taquilla', agencia: 'agencia', web: 'la página web' }

/** Importe en centavos (`"27000"`) como `Q 270.00`. */
export function quetzales(centavos: unknown): string | null {
  const n = Number(centavos)
  return Number.isFinite(n) ? `Q ${(n / 100).toFixed(2)}` : null
}

/** Líneas de detalle de una entrada, ya listas para mostrar (sin vacías). */
export function lineasDeDetalle(tipo: TipoBitacora, e: Pick<EntradaBitacora, 'operacion' | 'detalle'>): string[] {
  const d = e.detalle ?? {}
  const lineas: Array<string | null> = []

  if (tipo === 'salida') {
    if (e.operacion === 'cambio_bus') {
      lineas.push(`${d.de ? `Bus ${d.de}` : 'Sin bus'} → ${d.a ? `Bus ${d.a}` : 'sin bus'}`)
    } else if (d.de && d.a) {
      lineas.push(`Estado: ${ESTADOS[String(d.de)] ?? d.de} → ${ESTADOS[String(d.a)] ?? d.a}`)
    } else {
      if (d.trayecto) lineas.push(String(d.trayecto))
      if (d.bus) lineas.push(`Bus ${d.bus}`)
    }
  } else {
    if (e.operacion === 'creado') {
      lineas.push(texto(d.asiento) ? `Asiento ${d.asiento}` : null)
      lineas.push(texto(d.trayecto))
      lineas.push(quetzales(d.precio))
      lineas.push(d.canal ? `Vendido en ${CANALES[String(d.canal)] ?? d.canal}` : null)
      lineas.push(d.reasignadoDe ? `Reemplaza al boleto ${d.reasignadoDe}` : null)
    } else if (e.operacion === 'reasignado') {
      lineas.push(texto(d.asiento) ? `Asiento ${d.asiento} → asiento ${d.haciaAsiento ?? '?'}` : null)
      lineas.push(d.haciaSalida && d.haciaSalida !== d.salida ? `A la salida ${d.haciaSalida}` : null)
    } else if (e.operacion === 'anulado') {
      lineas.push(texto(d.asiento) ? `Asiento ${d.asiento}` : null)
      lineas.push(d.motivo ? `Motivo: ${d.motivo}` : null)
    }
    if (d.venta) lineas.push(`Venta ${d.venta}`)
  }

  return lineas.filter((l): l is string => l !== null)
}

/** Quién hizo la operación. */
export function quien(e: Pick<EntradaBitacora, 'usuario' | 'detalle'>): string {
  if (e.usuario) return e.usuario.nombre ?? e.usuario.username ?? 'Usuario eliminado'
  return e.detalle?.canal === 'web' ? 'Página web' : 'Sistema'
}
