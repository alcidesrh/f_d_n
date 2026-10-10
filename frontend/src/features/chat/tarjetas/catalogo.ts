/**
 * Tarjetas del chat por tipo de registro. Un tipo sin componente propio usa la
 * genérica (etiqueta + abrir en el formulario de la entidad). Para sumar un
 * tipo: su `Tarjeta` en el backend (`App\Chat\Tarjeta`) y aquí su componente.
 */
import type { Component } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import type { Adjunto } from '@/core/chat/types'
import { entitySlug } from '@/core/entities/slug'
import TarjetaBoleto from './TarjetaBoleto.vue'
import TarjetaBus from './TarjetaBus.vue'
import TarjetaSalida from './TarjetaSalida.vue'

export interface TipoTarjeta {
  nombre: string
  icono: string
  componente?: Component
  /** A dónde lleva "Abrir". */
  destino?: (a: Adjunto) => RouteLocationRaw
}

const formulario = (a: Adjunto): RouteLocationRaw => ({ name: 'entity-form', params: { entity: entitySlug(a.tipo), id: a.id } })

export const TARJETAS: Record<string, TipoTarjeta> = {
  BoletoAsiento: { nombre: 'Boleto', icono: 'confirmation-number-outline', componente: TarjetaBoleto, destino: formulario },
  Salida: { nombre: 'Salida', icono: 'directions-bus-outline', componente: TarjetaSalida, destino: (a) => ({ name: 'salidas', query: { ver: a.id } }) },
  Bus: { nombre: 'Bus', icono: 'airport-shuttle-outline', componente: TarjetaBus, destino: formulario },
  // Sin vista propia (tarjeta genérica): solo nombre e ícono.
  BoletoVenta: { nombre: 'Venta', icono: 'receipt-long-outline' },
  Cliente: { nombre: 'Cliente', icono: 'person-outline' },
  Piloto: { nombre: 'Piloto', icono: 'badge-outline' },
  Agencia: { nombre: 'Agencia', icono: 'storefront-outline' },
  Estacion: { nombre: 'Estación', icono: 'location-city' },
  Trayecto: { nombre: 'Trayecto', icono: 'route' },
  Empresa: { nombre: 'Empresa', icono: 'domain' },
  Factura: { nombre: 'Factura', icono: 'request-quote-outline' },
  Usuario: { nombre: 'Usuario', icono: 'account-circle-outline' },
  Enclave: { nombre: 'Enclave', icono: 'location-on-outline' },
}

/** Los que el selector de registros ofrece primero. */
export const HABITUALES = ['BoletoAsiento', 'Salida', 'BoletoVenta', 'Bus', 'Cliente', 'Piloto', 'Agencia', 'Estacion', 'Trayecto']

export function tipoTarjeta(tipo: string): Required<Pick<TipoTarjeta, 'nombre' | 'icono' | 'destino'>> & TipoTarjeta {
  const propia = TARJETAS[tipo]
  return { nombre: propia?.nombre ?? tipo.replace(/([a-z])([A-Z])/g, '$1 $2'), icono: propia?.icono ?? 'description-outline', destino: propia?.destino ?? formulario, componente: propia?.componente }
}
