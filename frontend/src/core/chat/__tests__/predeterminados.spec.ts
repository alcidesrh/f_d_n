import { describe, expect, it } from 'vitest'
import { borradorDe, carga, cuandoSale, faltantes, textoAudiencia, textoDias, type TipoMensaje } from '../predeterminados'

const tipo = (codigo: TipoMensaje['codigo'], extra: Partial<TipoMensaje> = {}): TipoMensaje => ({
  codigo,
  nombre: codigo,
  descripcion: '',
  exigeEmpresa: false,
  exigeRuta: false,
  conHora: false,
  conDias: false,
  exigeContenido: false,
  esAnomalia: false,
  ...extra,
})

describe('faltantes', () => {
  it('pide nombre, audiencia y contenido en el orden del formulario', () => {
    const b = borradorDe(null)
    expect(faltantes(b, tipo('libre', { exigeContenido: true, conHora: true }))).toEqual([
      'Póngale un nombre.',
      'Elija a quién va dirigido.',
      'Elija la hora a la que sale.',
      'Escriba un texto o adjunte una imagen o un archivo.',
    ])
  })

  it('la salida iniciada exige empresa y ruta, no contenido propio', () => {
    const b = { ...borradorDe(null), nombre: 'Salidas', grupos: [3] }
    expect(faltantes(b, tipo('salida_iniciada', { exigeEmpresa: true, exigeRuta: true }))).toEqual(['Elija la empresa.', 'Elija la ruta.'])
    expect(faltantes({ ...b, empresa: 1, ruta: 2 }, tipo('salida_iniciada', { exigeEmpresa: true, exigeRuta: true }))).toEqual([])
  })

  it('un archivo basta como contenido', () => {
    const b = { ...borradorDe(null), nombre: 'X', usuarios: [1], hora: '07:00', archivos: [{ id: 9, nombre: 'a.png', tipo: 'image/png', tamano: 1, ancho: 1, alto: 1, imagen: true, url: '' }] }
    expect(faltantes(b, tipo('libre', { exigeContenido: true }))).toEqual([])
  })
})

describe('carga', () => {
  it('manda ids y textos limpios', () => {
    const b = { ...borradorDe(null), nombre: '  Hola ', texto: ' x ', hora: '', archivos: [{ id: 4, nombre: '', tipo: '', tamano: 0, ancho: null, alto: null, imagen: false, url: '' }] }
    expect(carga(b)).toMatchObject({ nombre: 'Hola', texto: 'x', hora: null, archivos: [4] })
  })
})

describe('textoDias', () => {
  it('nombra rangos, listas y todos', () => {
    expect(textoDias([])).toBe('Todos los días')
    expect(textoDias([1, 2, 3, 4, 5, 6, 7])).toBe('Todos los días')
    expect(textoDias([5, 1, 2, 3, 4])).toBe('Lun a vie')
    expect(textoDias([1, 3, 5])).toBe('Lun, mié y vie')
    expect(textoDias([6])).toBe('Sáb')
    expect(textoDias([6, 7])).toBe('Sáb y dom')
  })
})

describe('cuandoSale y textoAudiencia', () => {
  it('describe la programación de cada tipo', () => {
    expect(cuandoSale({ tipo: 'libre', hora: '07:00', dias: [1, 2, 3, 4, 5], ruta: null }, '22:00')).toBe('Lun a vie a las 07:00')
    expect(cuandoSale({ tipo: 'salida_iniciada', hora: null, dias: [], ruta: { id: 1, nombre: 'Guatemala - Flores' } }, '22:00')).toBe('Al pasar la hora de cada salida de Guatemala - Flores')
    expect(cuandoSale({ tipo: 'ventas_dia', hora: null, dias: [], ruta: null }, '22:00')).toBe('Todos los días a las 22:00')
    expect(cuandoSale({ tipo: 'anomalia_tarifa', hora: null, dias: [], ruta: null }, '22:00')).toBe('Cada madrugada, si encuentra alguna')
  })

  it('cuenta usuarios, grupos y destinatarios', () => {
    expect(textoAudiencia({ usuarios: [{ id: 1, label: 'a' }], grupos: [{ id: 2, nombre: 'g' }, { id: 3, nombre: 'h' }], destinatarios: 9 })).toBe('1 usuario y 2 grupos · 9 destinatarios')
    expect(textoAudiencia({ usuarios: [], grupos: [], destinatarios: 0 })).toBe('Nadie · 0 destinatarios')
  })
})
