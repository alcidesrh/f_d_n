import { setActivePinia } from 'pinia'
import { pinia } from './pinia'
import { useUiStore } from '@/stores/ui'
import { useSchemaRepositoryStore } from '@/stores/schemaRepository'

export let ui: ReturnType<typeof useUiStore>
export let schemaRepository: ReturnType<typeof useSchemaRepositoryStore>

export async function initGlobalStores() {
  setActivePinia(pinia) // necesario para usar la store fuera de un componente
  ui = useUiStore()
  await ui.init()

  // Punto de entrada a la API GraphQL: se crea después del cliente Apollo
  // (src/lib/apollo/client.ts) y antes de montar cualquier vista. En la
  // primera creación parsea la introspección GraphQL; luego queda persistido.
  schemaRepository = useSchemaRepositoryStore()
  try {
    await schemaRepository.init()
    // const user = await getEntity('Usuario')
  } catch (error) {
    console.error('[schemaRepository] falló la carga del schema:', error)
  }
}
