/**
 * Catálogos del formulario de cliente (tipo de documento y nacionalidad),
 * leídos una vez por sesión de página: los usan la venta y las encomiendas.
 */
import { shallowRef } from 'vue'
import { http } from '@/core/http'

export interface CatalogosCliente {
  tiposDocumento: Array<{ id: number; nombre: string }>
  naciones: Array<{ id: number; nombre: string }>
}

const catalogos = shallowRef<CatalogosCliente | null>(null)
let pedido: Promise<void> | null = null

export function useCatalogosCliente() {
  pedido ??= http
    .get<CatalogosCliente>('/venta/clientes/catalogos', { silent: true })
    .then((c) => {
      catalogos.value = c
    })
    .catch(() => {
      // Sin catálogos el formulario sigue: esos dos campos quedan vacíos.
      pedido = null
    })
  return catalogos
}
