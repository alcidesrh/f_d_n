/** Rutas con sus paradas, tarifas por trayecto y el mapa de tarifas. */
import type { Guia } from '@/core/ayuda/tipos'
import { EDITAR_RUTA, EDITAR_TARIFA, SALIDAS, TARIFAS } from './audiencias'

export const guiasRutas: Guia[] = [
  {
    id: 'ver-rutas',
    tema: 'rutas',
    titulo: 'Ver las rutas',
    resumen: 'El camino de cada bus: sus paradas en orden, de origen a destino.',
    para: [SALIDAS],
    pasos: [
      'Abra «Rutas» en el menú. Busque por nombre, origen, destino o código.',
      'Pulse una ruta para ver sus paradas. La primera es el origen y la última el destino.',
      'Cada par de paradas en orden es un trayecto vendible si tiene tarifa.',
    ],
    ojo: [
      'Una ruta no tiene empresa ni fecha: las salidas son las que la usan en un día concreto.',
    ],
    abrir: { etiqueta: 'Abrir Rutas', destino: { name: 'rutas' } },
    pantallas: ['rutas', 'ruta'],
    palabras: ['camino', 'paradas', 'recorrido', 'itinerario'],
  },
  {
    id: 'crear-o-cambiar-ruta',
    tema: 'rutas',
    titulo: 'Crear o cambiar una ruta',
    resumen: 'Paradas en orden, con minutos y kilómetros desde el origen.',
    para: [EDITAR_RUTA],
    pasos: [
      'En «Rutas», pulse «Nueva ruta» (o abra una existente).',
      'Escriba el nombre y elija el origen.',
      'Pulse «Agregar parada» y elija el lugar. Use las flechas para cambiar el orden y «Quitar» para borrarla.',
      'Para cada clase de bus que tarde distinto, pulse «Agregar clase» y ponga los minutos desde el origen en cada parada.',
      'Indique si en cada parada se puede subir y bajar.',
      'Pulse «Crear ruta» o «Guardar cambios».',
    ],
    ojo: [
      'Al guardar, cada par de paradas en orden queda como trayecto: si no tiene tarifa, no se vende.',
      'Las salidas futuras sin boletos toman el nuevo horario. Las que ya tienen boletos conservan el suyo.',
      'Para una ruta de regreso, use la opción de ruta de regreso en el editor en lugar de armarla de nuevo.',
    ],
    abrir: { etiqueta: 'Abrir Rutas', destino: { name: 'rutas' } },
    pantallas: ['ruta', 'rutas'],
    palabras: ['nueva ruta', 'parada', 'agregar parada', 'orden de paradas', 'minutos', 'km'],
  },
  {
    id: 'tarifas-por-trayecto',
    tema: 'rutas',
    titulo: 'Tarifas por trayecto',
    resumen: 'Qué cuesta cada tramo, y cuál tarifa se aplica cuando hay varias.',
    para: [TARIFAS],
    antes: 'Las tarifas se llevaban por empresa y por clase de bus, sin horario.',
    pasos: [
      'Abra «Tarifas de boletos» en el menú y use el filtro: origen, destino, empresa, clase de bus, bus, usuario y horario.',
      'Para una tarifa nueva, pulse «Nueva tarifa»: trayecto, clase de asiento, precio (desde – hasta), y desde cuándo rige.',
    ],
    ojo: [
      'Se aplica la tarifa vigente más reciente que encaja. Si varias encajan, gana la más específica: empresa, luego clase de bus, luego horario, luego bus.',
      'Una tarifa con horario puede cruzar la medianoche.',
      'Lo que no tiene tarifa no se vende.',
    ],
    abrir: {
      etiqueta: 'Abrir Tarifas de boletos',
      destino: { name: 'entity-list', params: { entity: 'boleto-tarifa' } },
    },
    palabras: ['precio', 'tarifa', 'costo', 'boleto cuesta', 'especificidad'],
  },
  {
    id: 'nueva-tarifa',
    tema: 'rutas',
    titulo: 'Crear o cambiar una tarifa',
    resumen: 'Un precio nuevo para un trayecto, desde una fecha.',
    para: [EDITAR_TARIFA],
    pasos: [
      'Abra «Tarifas de boletos» en el menú y pulse «Nueva tarifa», o elija una existente.',
      'Elija el trayecto, la clase de asiento y, si aplica, la empresa, la clase de bus, el bus o el horario.',
      'Escriba el precio (desde – hasta) y la fecha «Vigente desde». Luego pulse «Guardar».',
    ],
    ojo: [
      'No borre una tarifa vigente para cambiar el precio: cree una nueva con otra fecha. Así queda el historial de lo que se cobró.',
      'Las ventas ya hechas conservan el precio con el que se hicieron.',
    ],
    palabras: ['cambiar precio', 'subir precio', 'tarifa nueva'],
  },
  {
    id: 'mapa-de-tarifas',
    tema: 'rutas',
    titulo: 'Ver las tarifas en el mapa',
    resumen: 'Los precios de cada trayecto sobre el mapa.',
    para: [SALIDAS],
    pasos: [
      'Abra «Tarifas» (mapa) en el menú.',
      'Elija un enclave de origen o de destino y el mapa muestra sus trayectos con su precio.',
      'Use «Invertir origen y destino» para ver el sentido contrario.',
    ],
    ojo: ['El mapa es para revisar precios; para cambiarlos use la lista de tarifas.'],
    abrir: { etiqueta: 'Abrir mapa de tarifas', destino: { name: 'tarifas-mapa' } },
    pantallas: ['tarifas-mapa'],
    palabras: ['mapa', 'precios', 'donde cuesta'],
  },
]
