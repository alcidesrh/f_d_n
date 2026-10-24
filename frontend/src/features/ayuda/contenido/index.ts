/**
 * Índice del manual: temas en su orden y todas las guías. Un tema nuevo = un
 * archivo más aquí. Los temas se ordenan como se trabaja: primero lo de todos,
 * luego la operación, después la administración.
 */
import type { Guia, Tema } from '@/core/ayuda/tipos'
import { guiasAccesos } from './accesos'
import { guiasAdministracion } from './administracion'
import { guiasAgencias } from './agencias'
import { guiasAutorizaciones } from './autorizaciones'
import { guiasBoletos } from './boletos'
import { guiasChat } from './chat'
import { guiasConceptos } from './conceptos'
import { guiasConfiguracion } from './configuracion'
import { guiasDatos } from './datos'
import { guiasEmpezar } from './empezar'
import { guiasEncomiendas } from './encomiendas'
import { guiasReportes } from './reportes'
import { guiasRutas } from './rutas'
import { guiasSalidas } from './salidas'
import { guiasVenta } from './venta'
import { guiasWeb } from './web'

export const TEMAS: Tema[] = [
  { id: 'empezar', titulo: 'Primeros pasos', icono: 'home-outline' },
  { id: 'venta', titulo: 'Venta de boletos', icono: 'point-of-sale' },
  { id: 'boletos', titulo: 'Boletos y abordaje', icono: 'confirmation-number-outline' },
  { id: 'salidas', titulo: 'Salidas y buses', icono: 'directions-bus-outline' },
  { id: 'rutas', titulo: 'Rutas y tarifas', icono: 'alt-route' },
  { id: 'encomiendas', titulo: 'Encomiendas', icono: 'inventory-2-outline' },
  { id: 'agencias', titulo: 'Agencias', icono: 'storefront-outline' },
  { id: 'web', titulo: 'Venta en línea', icono: 'language' },
  { id: 'reportes', titulo: 'Reportes y tablero', icono: 'summarize-outline' },
  { id: 'autorizaciones', titulo: 'Autorizaciones', icono: 'verified-user-outline' },
  { id: 'chat', titulo: 'Mensajes', icono: 'forum-outline' },
  { id: 'datos', titulo: 'Listados y formularios', icono: 'table-rows-outline' },
  { id: 'administracion', titulo: 'Administración', icono: 'apps-outline' },
  { id: 'configuracion', titulo: 'Configuración', icono: 'tune' },
  { id: 'accesos', titulo: 'Usuarios y accesos', icono: 'shield-person-outline' },
  { id: 'conceptos', titulo: 'Conceptos del negocio', icono: 'menu-book-outline' },
]

export const GUIAS: Guia[] = [
  ...guiasEmpezar,
  ...guiasVenta,
  ...guiasBoletos,
  ...guiasSalidas,
  ...guiasRutas,
  ...guiasEncomiendas,
  ...guiasAgencias,
  ...guiasWeb,
  ...guiasReportes,
  ...guiasAutorizaciones,
  ...guiasChat,
  ...guiasDatos,
  ...guiasAdministracion,
  ...guiasConfiguracion,
  ...guiasAccesos,
  ...guiasConceptos,
]
