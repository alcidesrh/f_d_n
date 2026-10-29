/** Autorizaciones: pedir un pase de un solo uso y resolverlo. */
import type { Guia } from '@/core/ayuda/tipos'
import { AUTORIZAR } from './audiencias'

export const guiasAutorizaciones: Guia[] = [
  {
    id: 'pedir-autorizacion',
    tema: 'autorizaciones',
    titulo: 'Pedir una autorización',
    resumen: 'Cuando el sistema le impide algo, un supervisor puede dejarle hacerlo una vez.',
    para: [],
    pasos: [
      'Cuando una operación no se deja hacer y el aviso ofrece «Pedir autorización», pulse el botón.',
      'El aviso dice qué quedó bloqueado y qué se pide. Escriba el motivo y pulse «Pedir».',
      'Espere la respuesta: le llega un aviso en el chat y la campana de la cabecera muestra su solicitud.',
      'Cuando la aprueban, repita la operación. El pase se usa solo y queda una sola vez.',
    ],
    ojo: [
      'Un pase vence a las 24 horas si no lo usa.',
      'Puede retirar su solicitud mientras no la hayan resuelto.',
    ],
    abrir: { etiqueta: 'Abrir Autorizaciones', destino: { name: 'autorizaciones' } },
    pantallas: ['autorizaciones'],
    palabras: ['pase', 'permiso temporal', 'supervisor', 'aprobacion', 'autorizar'],
  },
  {
    id: 'mis-solicitudes',
    tema: 'autorizaciones',
    titulo: 'Ver mis solicitudes de autorización',
    resumen: 'Lo que pidió, en qué quedó y cuándo vence.',
    para: [],
    pasos: [
      'Abra «Autorizaciones» desde la campana de la cabecera.',
      'En «Mis solicitudes» ve las suyas: pendientes, aprobadas (con el pase sin usar), rechazadas y vencidas.',
      'Pulse una solicitud para ver el motivo que dejó el supervisor.',
    ],
    palabras: ['mi solicitud', 'estado de autorizacion', 'pendiente'],
  },
  {
    id: 'resolver-autorizaciones',
    tema: 'autorizaciones',
    titulo: 'Aprobar o rechazar autorizaciones',
    resumen: 'Las solicitudes que esperan una respuesta de usted.',
    para: [AUTORIZAR],
    pasos: [
      'Abra «Autorizaciones». La pestaña «Por resolver» tiene lo que espera su respuesta; «Historial» lo ya resuelto.',
      'Pulse una solicitud para revisar qué se pide, quién lo pide, dónde y el motivo.',
      'Pulse «Aprobar» para autorizarla (puede dejar una nota) o «Rechazar» (el motivo es obligatorio).',
    ],
    ojo: [
      'Apruebe solo lo que pueda verificar en el momento: el pase se usa una vez y el sistema no lo pide de nuevo.',
      'Cada decisión queda en la bitácora de la operación.',
      'La campana de la cabecera cuenta lo que espera su respuesta.',
    ],
    abrir: { etiqueta: 'Abrir Autorizaciones', destino: { name: 'autorizaciones' } },
    pantallas: ['autorizaciones'],
    palabras: ['aprobar', 'rechazar', 'supervisor', 'por resolver', 'historial'],
  },
]
