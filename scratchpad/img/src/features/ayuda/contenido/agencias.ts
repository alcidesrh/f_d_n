/** Agencias: registrar depósitos, acreditarlos o rechazarlos, y consultar saldos. */
import type { Guia } from '@/core/ayuda/tipos'
import { AGENCIA_ACREDITAR, AGENCIA_DEPOSITAR } from './audiencias'

export const guiasAgencias: Guia[] = [
  {
    id: 'registrar-deposito',
    tema: 'agencias',
    titulo: 'Registrar un depósito de agencia',
    resumen: 'La boleta del banco, para que la agencia pueda vender con ese saldo.',
    para: [AGENCIA_DEPOSITAR],
    antes: 'La boleta se anotaba en un libro y el saldo se subía a mano.',
    pasos: [
      'Abra «Registrar depósito» en el menú (o desde «Depósitos»).',
      'Elija la agencia. Una agencia solo ve la suya.',
      'Escriba el número de boleta del banco, el importe en quetzales (Q) y una observación si hace falta.',
      'Pulse «Registrar». El depósito queda pendiente de acreditar.',
    ],
    ojo: [
      'Registrar no sube el saldo: el saldo se mueve solo al acreditar el depósito.',
      'Un número de boleta no se repite en la misma agencia, salvo si el depósito anterior se rechazó.',
    ],
    abrir: {
      etiqueta: 'Abrir Registrar depósito',
      destino: { name: 'agencia-depositos-registrar' },
    },
    pantallas: ['agencia-depositos-registrar'],
    palabras: ['boleta', 'banco', 'deposito', 'recarga', 'pago de agencia'],
  },
  {
    id: 'buscar-depositos',
    tema: 'agencias',
    titulo: 'Buscar depósitos',
    resumen: 'Los depósitos registrados, por estado, agencia o fecha.',
    para: [AGENCIA_DEPOSITAR],
    pasos: [
      'Abra «Depósitos» en el menú.',
      'Filtre por estado, agencia o fecha, y pulse «Filtrar».',
      'Con un depósito elegido, «Acciones del depósito elegido» muestra Ver y, si tiene el permiso, Acreditar y Rechazar.',
    ],
    palabras: ['buscar deposito', 'pendientes', 'estado del deposito'],
  },
  {
    id: 'acreditar-o-rechazar-deposito',
    tema: 'agencias',
    titulo: 'Acreditar o rechazar un depósito',
    resumen: 'Confirmar que el dinero llegó y subir el saldo, o rechazarlo con motivo.',
    para: [AGENCIA_ACREDITAR],
    pasos: [
      'En «Depósitos», elija el depósito pendiente y revise la boleta.',
      'Para aceptarlo, pulse «Acreditar»: el saldo sube y se suma la bonificación de la agencia, si tiene.',
      'Para no aceptarlo, pulse «Rechazar» y escriba el motivo.',
    ],
    ojo: [
      'Acreditar es lo único que mueve el saldo de una agencia. Un depósito rechazado no suma nada.',
      'La agencia recibe un aviso en el chat cuando su saldo se acredita.',
    ],
    palabras: ['aprobar deposito', 'confirmar pago', 'rechazar deposito', 'bonificacion'],
  },
  {
    id: 'saldos-de-agencias',
    tema: 'agencias',
    titulo: 'Ver los saldos de las agencias',
    resumen: 'Cuánto tiene cada agencia para vender, y sus movimientos.',
    para: [AGENCIA_DEPOSITAR, AGENCIA_ACREDITAR],
    pasos: [
      'Abra «Saldos» en el menú Agencia.',
      'Cada agencia aparece en una tarjeta con su saldo. Los totales se muestran por moneda.',
      'Pulse «Ver» en una agencia para sus movimientos: depósitos, ventas y bonificaciones.',
    ],
    ojo: [
      'El saldo nunca es negativo: una agencia sin saldo no puede vender hasta que se acredite un depósito.',
    ],
    abrir: { etiqueta: 'Abrir Saldos', destino: { name: 'agencia-saldos' } },
    pantallas: ['agencia-saldos', 'agencia-depositos'],
    palabras: ['saldo', 'cuanto tiene', 'movimientos', 'estado de cuenta'],
  },
]
