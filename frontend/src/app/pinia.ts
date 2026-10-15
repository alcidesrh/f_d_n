// core/pinia.ts
import { createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'
import { alcanceSesion, almacenPorUsuario } from '@/core/auth/alcanceSesion'

export const pinia = createPinia()
// Antes que la persistencia: separa lo de cada usuario (y descarta al cambiar de usuario).
pinia.use(alcanceSesion)
pinia.use(createPersistedState({ storage: almacenPorUsuario }))
