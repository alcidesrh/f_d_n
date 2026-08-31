import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { RouteRecordRaw } from 'vue-router'
import {
  toVueRouteDTO,
  extractVueRoutes,
  syncVueRoutes,
  type VueRouteDTO,
} from '@/utils/vueRoutesSync'

describe('toVueRouteDTO', () => {
  it('ignora rutas sin nombre', () => {
    expect(toVueRouteDTO({ path: '/sin-nombre', component: {} } as RouteRecordRaw)).toBeNull()
  })

  it('convierte una ruta simple', () => {
    const result = toVueRouteDTO({
      path: '/menu/:id/editar',
      name: 'menu-edit',
      component: {},
    } as RouteRecordRaw)

    expect(result).toEqual({ name: 'menu-edit', path: '/menu/:id/editar' })
  })

  it('incluye los hijos recursivamente y omite hijos sin nombre', () => {
    const result = toVueRouteDTO({
      path: '/padre',
      name: 'padre',
      component: {},
      children: [
        { path: '/hijo', name: 'hijo', component: {} },
        { path: '/sin-hijo', component: {} },
        {
          path: '/nieto',
          name: 'nieto',
          component: {},
          children: [{ path: '/bisnieto', name: 'bisnieto', component: {} }],
        },
      ],
    } as RouteRecordRaw)

    expect(result).toEqual({
      name: 'padre',
      path: '/padre',
      children: [
        { name: 'hijo', path: '/hijo' },
        {
          name: 'nieto',
          path: '/nieto',
          children: [{ name: 'bisnieto', path: '/bisnieto' }],
        },
      ],
    })
  })
})

describe('extractVueRoutes', () => {
  it('filtra rutas sin nombre del árbol', () => {
    const routes: RouteRecordRaw[] = [
      { path: '/', name: 'dashboard', component: {} } as RouteRecordRaw,
      { path: '/sin-nombre', component: {} } as RouteRecordRaw,
      {
        path: '/group',
        children: [{ path: '/child', name: 'child', component: {} }],
      } as RouteRecordRaw,
    ]

    const result = extractVueRoutes(routes)

    expect(result).toEqual([
      { name: 'dashboard', path: '/' },
      { name: 'child', path: '/child' },
    ])
  })
})

describe('syncVueRoutes', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
  })

  it('devuelve ok=false sin lanzar cuando el backend responde error', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: false,
      status: 500,
      statusText: 'Internal Server Error',
    } as Response)

    const result = await syncVueRoutes([])

    expect(result).toEqual({ ok: false, count: 0, error: 'HTTP 500 Internal Server Error' })
  })

  it('publica el árbol de rutas y devuelve ok', async () => {
    const fetchMock = vi.mocked(fetch)
    fetchMock.mockResolvedValue({ ok: true, status: 200 } as Response)

    const routes: VueRouteDTO[] = [{ name: 'dashboard', path: '/' }]
    const result = await syncVueRoutes(routes)

    expect(result).toEqual({ ok: true, count: 1 })

    const [, init] = fetchMock.mock.calls[0]
    const body = JSON.parse((init!.body as string) ?? '')
    expect(body.routes).toEqual(routes)
    expect(init!.method).toBe('POST')
    expect((init!.headers as Record<string, string>)['Content-Type']).toBe('application/json')
  })
})
