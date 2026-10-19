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

const MODALIDADES: Record<string, string> = { pagada: 'Pagada', por_cobrar: 'Por cobrar', interna: 'Interna (sin cobro)', con_boleto: 'Con boleto' }

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
      // Con boletos, el bus nuevo tiene la misma distribución y pasan por número (ADR-031).
      if (d.asientosPorNumero) lineas.push(`${d.asientosPorNumero} asiento(s) pasados al mismo número`)
    } else if (d.de && d.a) {
      lineas.push(`Estado: ${ESTADOS[String(d.de)] ?? d.de} → ${ESTADOS[String(d.a)] ?? d.a}`)
    } else {
      // `trayecto`: entradas anteriores a ADR-030 (la bitácora no se reescribe).
      if (d.ruta ?? d.trayecto) lineas.push(String(d.ruta ?? d.trayecto))
      if (d.bus) lineas.push(`Bus ${d.bus}`)
    }
  } else if (tipo === 'encomienda') {
    // ADR-035: lo que anota `RegistradorBitacora` para una encomienda.
    if (e.operacion === 'recibida' && !d.de) {
      lineas.push(d.origen && d.destino ? `${d.origen} → ${d.destino}` : null)
      lineas.push(d.modalidad ? `${MODALIDADES[String(d.modalidad)] ?? d.modalidad}` : null)
      lineas.push(texto(d.bultos) ? `${d.bultos} ${Number(d.bultos) === 1 ? 'bulto' : 'bultos'}` : null)
      lineas.push(Number(d.total) > 0 ? quetzales(d.total) : null)
      lineas.push(d.venta ? `Con la venta ${d.venta}` : null)
    }
    lineas.push(texto(d.estacion) ? `En ${d.estacion}` : null)
    lineas.push(texto(d.salida) ? `Salida ${d.salida}` : null)
    lineas.push(texto(d.retira) ? `La retiró ${d.retira}` : null)
    lineas.push(d.motivo ? `Motivo: ${d.motivo}` : null)
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
    } else if (e.operacion === 'anulado' || e.operacion === 'cancelado' || e.operacion === 'pospuesto') {
      lineas.push(texto(d.asiento) ? `Asiento ${d.asiento}` : null)
      lineas.push(d.motivo ? `Motivo: ${d.motivo}` : null)
    } else if (e.operacion === 'chequeado' || e.operacion === 'transito' || e.operacion === 'finalizado' || e.operacion === 'revertido') {
      lineas.push(texto(d.asiento) ? `Asiento ${d.asiento}` : null)
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
