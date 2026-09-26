import { describe, expect, it, vi } from 'vitest'
import {
  createEntityFormExtensionHost,
  EntityFormExtensionError,
  scopedEntityFormExtensionHost,
} from '../formExtension'

describe('host de secciones extra del formulario', () => {
  it('validate devuelve la primera sección que bloquea, con su clave', () => {
    const host = createEntityFormExtensionHost()
    scopedEntityFormExtensionHost(host, 'a').register({ validate: () => null })
    scopedEntityFormExtensionHost(host, 'croquis').register({ validate: () => 'Hay errores' })
    expect(host.validate()).toEqual({ key: 'croquis', message: 'Hay errores' })
  })

  it('afterSave corre en orden y un fallo lleva la clave de la sección', async () => {
    const host = createEntityFormExtensionHost()
    const primera = vi.fn<(item: Record<string, unknown>) => Promise<void>>(async () => {})
    scopedEntityFormExtensionHost(host, 'a').register({ afterSave: primera })
    scopedEntityFormExtensionHost(host, 'croquis').register({
      afterSave: async () => {
        throw new Error('422')
      },
    })
    const error = await host.afterSave({ id: '/api/buses/1' }).catch((e: unknown) => e)
    expect(primera).toHaveBeenCalledWith({ id: '/api/buses/1' })
    expect(error).toBeInstanceOf(EntityFormExtensionError)
    expect(error).toMatchObject({ key: 'croquis', message: '422' })
  })

  it('una sección desregistrada ya no participa', () => {
    const host = createEntityFormExtensionHost()
    const quitar = host.register({ validate: () => 'no' })
    quitar()
    expect(host.validate()).toBeNull()
  })
})
