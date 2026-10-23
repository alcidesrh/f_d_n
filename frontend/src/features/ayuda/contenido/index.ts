/** Índice del manual: temas en orden y todas las guías. Un tema nuevo = un archivo más aquí. */
import type { Guia, Tema } from '@/core/ayuda/tipos'
import { guiasCuenta, guiasUsuarios } from './usuarios'

export const TEMAS: Tema[] = [
  { id: 'cuenta', titulo: 'Mi cuenta', icono: 'manage-accounts-outline' },
  { id: 'usuarios', titulo: 'Usuarios y accesos', icono: 'shield-person-outline' },
]

export const GUIAS: Guia[] = [...guiasCuenta, ...guiasUsuarios]
