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

/** Una asignación anotada (`BitacoraAcceso`): rol y alcance en una línea, la vigencia y el motivo aparte. */
function asignacion(d: Record<string, unknown>): Array<string | null> {
  const lista = (v: unknown, que: string) => (v === 'todas' || v === null || v === undefined ? `todas las ${que}` : Array.isArray(v) && v.length ? `${que} ${v.join(', ')}` : `ninguna ${que.replace(/s$/, '')}`)
  return [
    d.rol ? `${d.rol} · ${lista(d.empresas, 'empresas')} · ${lista(d.estaciones, 'estaciones')}` : null,
    d.desde || d.hasta ? `Vigencia: ${texto(d.desde) ?? '…'} a ${texto(d.hasta) ?? '…'}` : null,
    d.motivo ? `Motivo: ${d.motivo}` : null,
  ]
}

/** Líneas de detalle de una entrada, ya listas para mostrar (sin vacías). */
export function lineasDeDetalle(tipo: TipoBitacora, e: Pick<EntradaBitacora, 'operacion' | 'detalle'>): string[] {
  const d = e.detalle ?? {}
  const lineas: Array<string | null> = []

  if (tipo === 'usuario') {
    if (e.operacion === 'asignacion_modificada') {
      const [antes, ahora] = [asignacion((d.antes ?? {}) as Record<string, unknown>), asignacion((d.ahora ?? {}) as Record<string, unknown>)]
      lineas.push(antes[0] ? `Antes: ${antes[0]}` : null, ahora[0] ? `Ahora: ${ahora[0]}` : null, ahora[1] ?? null, ahora[2] ?? null)
    } else if (e.operacion === 'estacion_trabajo') {
      lineas.push(`${texto(d.antes) ?? 'Sin estación'} → ${texto(d.ahora) ?? 'sin estación'}`)
    } else {
      lineas.push(...asignacion(d))
    }
    return lineas.filter((l): l is string => l !== null)
  }
  if (tipo === 'rol') {
    const nombres = (v: unknown) => (Array.isArray(v) && v.length ? v.join(', ') : null)
    lineas.push(texto(d.nombre))
    if (e.operacion === 'modificado') {
      lineas.push(nombres(d.agregadas) ? `Agrega: ${nombres(d.agregadas)}` : null, nombres(d.quitadas) ? `Quita: ${nombres(d.quitadas)}` : null, d.limites ? 'Cambió los límites' : null)
    } else if (Array.isArray(d.capacidades)) {
      lineas.push(`${d.capacidades.length} capacidades`)
    }
    return lineas.filter((l): l is string => l !== null)
  }

  // Autorizaciones (ADR-040): el motivo de quien la pidió, la nota o el motivo de quien la resolvió.
  if (tipo === 'autorizacion') {
    lineas.push(d.motivo ? `Motivo: ${d.motivo}` : null, d.nota ? `Nota: ${d.nota}` : null)
    return lineas.filter((l): l is string => l !== null)
  }

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
    if (e.operacion === 'ruta_cambiada') lineas.push(`Ruta: ${texto(d.antes) ?? 'sin ruta'} → ${texto(d.ruta) ?? 'sin ruta'}`)
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
    } else if (e.operacion.startsWith('autorizacion_')) {
      lineas.push(d.autorizacion ? `Autorización #${d.autorizacion}: ${texto(d.operacion) ?? ''} (${texto(d.dispensa)?.toLowerCase() ?? ''})` : null)
      lineas.push(d.motivo ? `Motivo: ${d.motivo}` : null, d.nota ? `Nota: ${d.nota}` : null)
    }
    // Anulado o reasignado con una autorización (ADR-040).
    if (Array.isArray(d.autorizaciones) && d.autorizaciones.length) lineas.push(`Con la autorización ${d.autorizaciones.map((a) => `#${a}`).join(', ')}`)
    if (d.venta) lineas.push(`Venta ${d.venta}`)
  }

  return lineas.filter((l): l is string => l !== null)
}

/** Quién hizo la operación. */
export function quien(e: Pick<EntradaBitacora, 'usuario' | 'detalle'>): string {
  if (e.usuario) return e.usuario.nombre ?? e.usuario.username ?? 'Usuario eliminado'
  return e.detalle?.canal === 'web' ? 'Página web' : 'Sistema'
}
