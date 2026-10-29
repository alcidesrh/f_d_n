import { describe, expect, it } from 'vitest'
import { customFilterOrNull } from '@/core/entities/listCustomFilter'
import { argsFiltroTarifa, chipsFiltroTarifa, estadoFiltroTarifa, FILTRO_TARIFA_VACIO, filtroTarifaDe } from '../filtro'

const guate = { value: '/api/enclaves/1', label: 'Guatemala' }
const flores = { value: '/api/enclaves/2', label: 'Flores' }

describe('filtro de tarifas', () => {
  it('vacío no tiene argumentos y se descarta', () => {
    expect(argsFiltroTarifa(FILTRO_TARIFA_VACIO)).toEqual({})
    expect(customFilterOrNull(estadoFiltroTarifa(FILTRO_TARIFA_VACIO))).toBeNull()
  })

  it('traduce cada término a su argumento de la colección', () => {
    const filtro = {
      ...FILTRO_TARIFA_VACIO,
      origen: guate,
      destino: flores,
      empresa: { value: '/api/empresas/3', label: 'FDN' },
      busClase: { value: '/api/bus_clases/4', label: 'Oro' },
      bus: { value: '/api/buses/5', label: '101' },
      usuario: { value: '/api/usuarios/6', label: 'ana' },
      horaDesde: '22:00',
      horaHasta: '02:00',
    }
    expect(argsFiltroTarifa(filtro)).toEqual({
      trayectoOrigen: '/api/enclaves/1',
      trayectoDestino: '/api/enclaves/2',
      empresa: '/api/empresas/3',
      busClase: '/api/bus_clases/4',
      bus: '/api/buses/5',
      usuario: '/api/usuarios/6',
      horarioDesde: '22:00',
      horarioHasta: '02:00',
    })
  })

  it('resume el trayecto y el horario en un chip cada uno', () => {
    expect(chipsFiltroTarifa({ ...FILTRO_TARIFA_VACIO, origen: guate, destino: flores, horaDesde: '08:00', horaHasta: '12:00' })).toEqual([
      { label: 'Trayecto', value: 'Guatemala ➔ Flores' },
      { label: 'Horario', value: '08:00–12:00' },
    ])
    expect(chipsFiltroTarifa({ ...FILTRO_TARIFA_VACIO, destino: flores, horaHasta: '10:30' })).toEqual([
      { label: 'Destino', value: 'Flores' },
      { label: 'Hora', value: '10:30' },
    ])
  })

  it('normaliza lo guardado y descarta lo que no sirve', () => {
    expect(filtroTarifaDe(null)).toEqual(FILTRO_TARIFA_VACIO)
    expect(filtroTarifaDe({ origen: guate, destino: 'x', bus: { value: '' }, horaDesde: '25:00', horaHasta: '07:15:00' })).toEqual({
      ...FILTRO_TARIFA_VACIO,
      origen: guate,
      horaHasta: '07:15',
    })
  })

  it('lo guardado vuelve igual', () => {
    const filtro = { ...FILTRO_TARIFA_VACIO, origen: guate, horaDesde: '06:00' }
    expect(filtroTarifaDe(estadoFiltroTarifa(filtro).values)).toEqual(filtro)
  })
})
