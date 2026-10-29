/**
 * Quién ve una guía: la operación que describe (la capacidad que la exige).
 * El manual no las nombra como tales: la etiqueta dice el trabajo y el código
 * decide quién llega a la guía. Una sola definición por operación.
 */
import type { Audiencia } from '@/core/ayuda/tipos'

// Venta y boletos
export const VENTA: Audiencia = { etiqueta: 'Venta de boletos', exige: 'venta.vender' }
export const CORTESIA: Audiencia = { etiqueta: 'Cortesías', exige: 'venta.cortesia' }
export const SIN_FACTURA: Audiencia = { etiqueta: 'Venta sin factura', exige: 'venta.sin_factura' }
export const BOLETOS: Audiencia = { etiqueta: 'Boletos', exige: 'boleto.ver' }
export const CANCELAR_BOLETO: Audiencia = { etiqueta: 'Cancelar boletos', exige: 'boleto.cancelar' }
export const ANULAR_BOLETO: Audiencia = { etiqueta: 'Anular boletos', exige: 'boleto.anular' }
export const REASIGNAR: Audiencia = { etiqueta: 'Reasignar boletos', exige: 'boleto.reasignar' }
export const CHEQUEAR: Audiencia = { etiqueta: 'Chequeo al abordar', exige: 'boleto.chequear' }

// Salidas, rutas y tarifas
export const SALIDAS: Audiencia = { etiqueta: 'Salidas', exige: 'salida.ver' }
export const PROGRAMAR: Audiencia = { etiqueta: 'Programar salidas', exige: 'salida.crear' }
export const EDITAR_SALIDA: Audiencia = { etiqueta: 'Editar salidas', exige: 'salida.editar' }
export const ANULAR_SALIDA: Audiencia = { etiqueta: 'Anular salidas', exige: 'salida.cancelar' }
export const ELIMINAR_SALIDA: Audiencia = { etiqueta: 'Eliminar salidas', exige: 'salida.eliminar' }
export const ABORDAR: Audiencia = { etiqueta: 'Abordaje', exige: 'salida.abordar' }
export const INICIAR: Audiencia = { etiqueta: 'Iniciar salidas', exige: 'salida.iniciar' }
export const FINALIZAR: Audiencia = { etiqueta: 'Finalizar salidas', exige: 'salida.finalizar' }
export const EDITAR_RUTA: Audiencia = { etiqueta: 'Rutas', exige: 'ruta.editar' }
export const TARIFAS: Audiencia = { etiqueta: 'Tarifas', exige: 'tarifa.ver' }
export const EDITAR_TARIFA: Audiencia = { etiqueta: 'Tarifas', exige: 'tarifa.editar' }

// Encomiendas
export const ENCOMIENDAS: Audiencia = { etiqueta: 'Encomiendas', exige: 'encomienda.ver' }
export const REGISTRAR_ENCOMIENDA: Audiencia = {
  etiqueta: 'Registrar encomiendas',
  exige: 'encomienda.registrar',
}
export const ENCOMIENDA_INTERNA: Audiencia = {
  etiqueta: 'Encomiendas internas',
  exige: 'encomienda.interna',
}
export const EMBARCAR: Audiencia = {
  etiqueta: 'Embarcar encomiendas',
  exige: 'encomienda.embarcar',
}
export const ENTREGAR: Audiencia = {
  etiqueta: 'Entregar encomiendas',
  exige: 'encomienda.entregar',
}
export const ANULAR_ENCOMIENDA: Audiencia = {
  etiqueta: 'Anular encomiendas',
  exige: 'encomienda.anular',
}
export const ESPECIALES: Audiencia = {
  etiqueta: 'Catálogo de especiales',
  exige: 'encomienda.especiales',
}

// Agencias, reportes y página web
export const AGENCIA_DEPOSITAR: Audiencia = {
  etiqueta: 'Depósitos de agencias',
  exige: 'agencia.depositar',
}
export const AGENCIA_ACREDITAR: Audiencia = {
  etiqueta: 'Acreditar depósitos',
  exige: 'agencia.acreditar',
}
export const REPORTE_VENTAS: Audiencia = { etiqueta: 'Reportes de venta', exige: 'reporte.ventas' }
export const REPORTE_ENCOMIENDAS: Audiencia = {
  etiqueta: 'Reportes de encomiendas',
  exige: 'reporte.encomiendas',
}
export const PAGINA: Audiencia = { etiqueta: 'Venta en línea', exige: 'pagina.administrar' }
export const AUTORIZAR: Audiencia = {
  etiqueta: 'Aprobar autorizaciones',
  exige: 'autorizacion.aprobar',
}
export const BITACORA: Audiencia = { etiqueta: 'Bitácoras', exige: 'bitacora.ver' }

// Configuración, accesos y usuarios
export const CONFIG: Audiencia = { etiqueta: 'Configuración', exige: 'config.editar' }
export const MENUS: Audiencia = { etiqueta: 'Menús', exige: 'menu.editar' }
export const MIGRACION: Audiencia = { etiqueta: 'Migración', exige: 'migracion.ejecutar' }
export const ACCESOS_VER: Audiencia = { etiqueta: 'Accesos', exige: 'iam.ver' }
export const ACCESOS_ASIGNAR: Audiencia = { etiqueta: 'Asignar accesos', exige: 'iam.asignar' }
export const ACCESOS_ROLES: Audiencia = { etiqueta: 'Definir roles', exige: 'iam.roles' }
export const USUARIOS_VER: Audiencia = { etiqueta: 'Usuarios', exige: 'usuario.ver' }
export const USUARIOS_EDITAR: Audiencia = { etiqueta: 'Usuarios', exige: 'usuario.editar' }
