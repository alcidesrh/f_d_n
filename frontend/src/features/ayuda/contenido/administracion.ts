/** Administración: la pantalla de accesos directos del sistema. */
import type { Guia } from '@/core/ayuda/tipos'
import { CONFIG } from './audiencias'

export const guiasAdministracion: Guia[] = [
  {
    id: 'pantalla-administracion',
    tema: 'administracion',
    titulo: 'La pantalla de Administración',
    resumen: 'Las cajas de colores con los listados y pantallas que más se usan.',
    para: [],
    antes:
      'Era el «Administrar» del sistema anterior: los mismos accesos, ahora en cajas que se pueden ordenar.',
    pasos: [
      'Abra «Administración» en el menú.',
      'Cada caja agrupa enlaces: un listado, una pantalla o la opción de «Agregar nuevo».',
      'Escriba en la búsqueda para encontrar un enlace. Con Enter abre el primero que coincide.',
    ],
    ojo: ['Solo ve las cajas y enlaces que le corresponden a su cuenta.'],
    abrir: { etiqueta: 'Abrir Administración', destino: { name: 'administracion' } },
    pantallas: ['administracion'],
    palabras: ['administrar', 'accesos directos', 'cajas', 'listados'],
  },
  {
    id: 'personalizar-administracion',
    tema: 'administracion',
    titulo: 'Personalizar las cajas de Administración',
    resumen: 'Crear, ordenar y ocultar cajas y enlaces, y elegir quién los ve.',
    para: [CONFIG],
    pasos: [
      'En «Administración», pulse «Personalizar».',
      'Para una caja nueva, pulse «Nueva caja», escriba el título, elija el color y el ícono, y quién la ve.',
      'Para un enlace, abra la caja y agregue uno: elija la pantalla con sus datos o escriba una ruta.',
      'Arrastre las cajas y los enlaces para ordenarlos; pase un enlace a otra caja arrastrándolo.',
      'Pulse «Listo» al terminar. Cada cambio se guarda al momento.',
    ],
    ojo: [
      'Ocultar una caja no la borra: sigue en la configuración.',
      'Quién ve cada caja y cada enlace se decide al crearlo o al editarlo.',
    ],
    abrir: { etiqueta: 'Abrir Administración', destino: { name: 'administracion' } },
    pantallas: ['administracion'],
    palabras: ['cajas', 'personalizar', 'ordenar', 'color', 'enlace nuevo'],
  },
]
