import { describe, it, expect } from 'vitest'
import type { FormKitSchemaNode } from '@formkit/core'
import {
  buildFieldOptions,
  capitalizeLabel,
  humanizeLabel,
  hydrateInitialValues,
  inferInputType,
  serializeEntityForm,
  serializeSubmitValue,
  validationFor,
  type FormFieldSource,
} from '@/utils/formkit/schemaSerializer'
import type { AgnosticOption } from '@/lib/apollo/types'

function field(
  overrides: Partial<FormFieldSource> & Pick<FormFieldSource, 'name' | 'namedType'>,
): FormFieldSource {
  return {
    kind: 'SCALAR',
    required: false,
    isList: false,
    isRelation: false,
    enumValues: [],
    ...overrides,
  }
}

/** Nodo de campo (sintaxis corta) con `name` dado, buscando en el árbol anidado. */
function findField(nodes: FormKitSchemaNode[], name: string): Record<string, unknown> {
  const stack: unknown[] = [...nodes]
  while (stack.length > 0) {
    const node = stack.pop()
    if (node && typeof node === 'object') {
      const record = node as Record<string, unknown>
      if (record.$formkit && record.name === name) return record
      const children = record.children
      if (Array.isArray(children)) stack.push(...children)
    }
  }
  throw new Error(`campo "${name}" no encontrado en el schema`)
}

function findWrapper(nodes: FormKitSchemaNode[], name: string): FormKitSchemaNode {
  const stack: FormKitSchemaNode[] = [...nodes]
  while (stack.length > 0) {
    const node = stack.pop()!
    if (node && typeof node === 'object' && '$el' in node) {
      const record = node as { $el: string; children?: FormKitSchemaNode[] }
      const child = (record.children ?? []).find((c) => {
        if (!c || typeof c !== 'object') return false
        const inner = c as Record<string, unknown>
        return inner.$formkit && inner.name === name
      })
      if (child) return node
      stack.push(...(record.children ?? []))
    }
  }
  throw new Error(`wrapper del campo "${name}" no encontrado`)
}

const fullList: AgnosticOption[] = [
  { id: '/api/rutas/1', label: 'Ruta 1' },
  { id: '/api/rutas/2', label: 'Ruta 2' },
]

describe('humanizeLabel', () => {
  it('separa camelCase, snake/kebab y capitaliza', () => {
    expect(humanizeLabel('createdAt')).toBe('Created At')
    expect(humanizeLabel('numero_boleto')).toBe('Numero Boleto')
    expect(humanizeLabel('_id')).toBe('Id')
    expect(humanizeLabel('nombre')).toBe('Nombre')
  })
})

describe('capitalizeLabel', () => {
  it('capitaliza la primera letra y respeta el resto', () => {
    expect(capitalizeLabel('localidad')).toBe('Localidad')
    expect(capitalizeLabel('Número de boleto')).toBe('Número de boleto')
    expect(capitalizeLabel('CUIT')).toBe('CUIT')
  })
})

describe('inferInputType', () => {
  it('mapea cada tipo de campo al Custom Input registrado', () => {
    expect(inferInputType(field({ name: 'nombre', namedType: 'String' }))).toBe('InputText')
    expect(inferInputType(field({ name: 'total', namedType: 'Float' }))).toBe('InputNumber')
    expect(inferInputType(field({ name: 'activo', namedType: 'Boolean' }))).toBe('ToggleSwitch')
    expect(inferInputType(field({ name: 'creado', namedType: 'DateTime' }))).toBe('DatePicker')
    expect(inferInputType(field({ name: 'password', namedType: 'String' }))).toBe('Password')
    expect(inferInputType(field({ name: 'descripcion', namedType: 'String' }))).toBe('TextArea')
    expect(
      inferInputType(
        field({ name: 'estado', namedType: 'Estado', kind: 'ENUM', enumValues: ['A'] }),
      ),
    ).toBe('Select')
    expect(
      inferInputType(field({ name: 'ruta', namedType: 'Ruta', kind: 'OBJECT', isRelation: true })),
    ).toBe('Select')
    expect(
      inferInputType(
        field({
          name: 'boletas',
          namedType: 'Boleta',
          kind: 'OBJECT',
          isRelation: true,
          isList: true,
        }),
      ),
    ).toBe('MultiSelect')
  })
})

describe('validationFor', () => {
  it('aplica required por nulabilidad y reglas por tipo/nombre', () => {
    expect(validationFor(field({ name: 'nombre', namedType: 'String', required: true }))).toBe(
      'required',
    )
    expect(validationFor(field({ name: 'total', namedType: 'Float' }))).toBe('number')
    expect(validationFor(field({ name: 'total', namedType: 'Float', required: true }))).toBe(
      'required|number',
    )
    expect(validationFor(field({ name: 'correo', namedType: 'String' }))).toBe('email')
    expect(validationFor(field({ name: 'sitioWeb', namedType: 'String' }))).toBe('url')
    expect(validationFor(field({ name: 'nombre', namedType: 'String' }))).toBeUndefined()
  })
})

describe('buildFieldOptions', () => {
  it('mapea fullList de relaciones a { label, value: IRI }', () => {
    const entry = field({ name: 'ruta', namedType: 'Ruta', kind: 'OBJECT', isRelation: true })
    expect(buildFieldOptions(entry, fullList)).toEqual([
      { label: 'Ruta 1', value: '/api/rutas/1' },
      { label: 'Ruta 2', value: '/api/rutas/2' },
    ])
  })

  it('usa enumValues como opciones de enums', () => {
    const entry = field({
      name: 'estado',
      namedType: 'Estado',
      kind: 'ENUM',
      enumValues: ['ACTIVO', 'VENDIDO'],
    })
    expect(buildFieldOptions(entry, [])).toEqual([
      { label: 'ACTIVO', value: 'ACTIVO' },
      { label: 'VENDIDO', value: 'VENDIDO' },
    ])
  })
})

describe('serializeEntityForm', () => {
  const fields = [
    field({ name: 'numero', namedType: 'String', required: true }),
    field({ name: 'total', namedType: 'Float' }),
    field({ name: 'activo', namedType: 'Boolean' }),
    field({ name: 'fecha', namedType: 'Date' }),
    field({ name: 'password', namedType: 'String' }),
    field({ name: 'descripcion', namedType: 'String' }),
    field({ name: 'ruta', namedType: 'Ruta', kind: 'OBJECT', isRelation: true }),
    field({ name: 'boletas', namedType: 'Boleta', kind: 'OBJECT', isRelation: true, isList: true }),
    field({ name: 'estado', namedType: 'Estado', kind: 'ENUM', enumValues: ['A', 'B'] }),
  ]

  it('usa sintaxis corta $formkit con props planas y labels humanizados', () => {
    const schema = serializeEntityForm('Boleto', [field({ name: 'numero', namedType: 'String' })])
    expect(schema).toHaveLength(1)
    const node = findField(schema, 'numero')
    expect(node.$formkit).toBe('InputText')
    expect(node.name).toBe('numero')
    expect(node.label).toBe('Numero')
    expect(node.key).toContain('Boleto.numero')
  })

  it('aplica validaciones y valores iniciales hidratados', () => {
    const schema = serializeEntityForm(
      'Boleto',
      [field({ name: 'total', namedType: 'Float', required: true })],
      { values: { total: 42 } },
    )
    const node = findField(schema, 'total')
    expect(node.validation).toBe('required|number')
    expect(node.value).toBe(42)
  })

  it('configura InputNumber entero y DatePicker', () => {
    const schema = serializeEntityForm('Boleto', [
      field({ name: 'cantidad', namedType: 'Int' }),
      field({ name: 'fecha', namedType: 'Date' }),
    ])
    expect(findField(schema, 'cantidad')).toMatchObject({
      $formkit: 'InputNumber',
      minFractionDigits: 0,
      maxFractionDigits: 0,
    })
    expect(findField(schema, 'fecha')).toMatchObject({
      $formkit: 'DatePicker',
      dateFormat: 'dd/mm/yy',
      showIcon: true,
    })
  })

  it('serializa relaciones únicas como Select con filter y opciones', () => {
    const schema = serializeEntityForm(
      'Boleto',
      [field({ name: 'ruta', namedType: 'Ruta', kind: 'OBJECT', isRelation: true })],
      { relationOptions: { ruta: fullList } },
    )
    expect(findField(schema, 'ruta')).toMatchObject({
      $formkit: 'Select',
      filter: true,
      showClear: true,
      options: [
        { label: 'Ruta 1', value: '/api/rutas/1' },
        { label: 'Ruta 2', value: '/api/rutas/2' },
      ],
    })
  })

  it('serializa relaciones múltiples como MultiSelect con filter', () => {
    const schema = serializeEntityForm(
      'Boleto',
      [
        field({
          name: 'boletas',
          namedType: 'Boleta',
          kind: 'OBJECT',
          isRelation: true,
          isList: true,
        }),
      ],
      { relationOptions: { boletas: fullList } },
    )
    expect(findField(schema, 'boletas')).toMatchObject({
      $formkit: 'MultiSelect',
      filter: true,
      display: 'chip',
      options: [
        { label: 'Ruta 1', value: '/api/rutas/1' },
        { label: 'Ruta 2', value: '/api/rutas/2' },
      ],
    })
  })

  it('serializa enums como Select con sus valores', () => {
    const schema = serializeEntityForm('Boleto', [
      field({ name: 'estado', namedType: 'Estado', kind: 'ENUM', enumValues: ['A', 'B'] }),
    ])
    expect(findField(schema, 'estado')).toMatchObject({
      $formkit: 'Select',
      filter: true,
      options: [
        { label: 'A', value: 'A' },
        { label: 'B', value: 'B' },
      ],
    })
  })

  it('muestra id deshabilitado en update y habilitado en create', () => {
    const id = field({ name: 'id', namedType: 'ID' })
    const updateSchema = serializeEntityForm('Boleto', [id], { mode: 'update' })
    expect(findField(updateSchema, 'id').disabled).toBe(true)
    const createSchema = serializeEntityForm('Boleto', [id], { mode: 'create' })
    expect(findField(createSchema, 'id').disabled).toBeUndefined()
  })

  it('respeta labels por campo', () => {
    const schema = serializeEntityForm('Boleto', [field({ name: 'numero', namedType: 'String' })], {
      labels: { numero: 'Número de boleto' },
    })
    expect(findField(schema, 'numero').label).toBe('Número de boleto')
  })

  it('capitaliza labels configurados en minúscula', () => {
    const schema = serializeEntityForm('Boleto', [field({ name: 'numero', namedType: 'String' })], {
      labels: { numero: 'numero boleto' },
    })
    expect(findField(schema, 'numero').label).toBe('Numero boleto')
  })

  it('capitaliza labels de opciones de enums (el valor se envía intacto)', () => {
    const schema = serializeEntityForm('Boleto', [
      field({
        name: 'estado',
        namedType: 'Estado',
        kind: 'ENUM',
        enumValues: ['activo', 'vendido'],
      }),
    ])
    expect(findField(schema, 'estado').options).toEqual([
      { label: 'Activo', value: 'activo' },
      { label: 'Vendido', value: 'vendido' },
    ])
  })

  it('envuelve en grid responsive y TextArea a ancho completo', () => {
    const schema = serializeEntityForm('Boleto', [
      field({ name: 'nombre', namedType: 'String' }),
      field({ name: 'descripcion', namedType: 'String' }),
    ])
    const rows = schema.filter((node): node is FormKitSchemaNode =>
      Boolean(node && typeof node === 'object' && (node as { $el?: string }).$el === 'div'),
    )
    expect(rows.length).toBeGreaterThan(0)
    const first = rows[0] as { attrs?: { class?: string } }
    expect(first.attrs?.class).toContain('grid grid-cols-1 md:grid-cols-2')
    const wrapper = findWrapper(schema, 'descripcion') as { attrs?: { class?: string } }
    expect(wrapper.attrs?.class).toContain('md:col-span-2')
  })

  it('produce JSON plano (round-trip estable)', () => {
    const schema = serializeEntityForm('Boleto', fields, {
      relationOptions: { ruta: fullList, boletas: fullList },
    })
    expect(JSON.parse(JSON.stringify(schema))).toEqual(schema)
  })
})

describe('hydrateInitialValues', () => {
  const fields = [
    field({ name: 'ruta', namedType: 'Ruta', kind: 'OBJECT', isRelation: true }),
    field({ name: 'boletas', namedType: 'Boleta', kind: 'OBJECT', isRelation: true, isList: true }),
    field({ name: 'fecha', namedType: 'Date' }),
  ]

  it('reduce relaciones { id, label } a IRIs (lista y única)', () => {
    const values = hydrateInitialValues(fields, {
      ruta: { id: '/api/rutas/1', label: 'Ruta 1' },
      boletas: [{ id: '/api/boletas/1' }, { id: '/api/boletas/2' }, null],
    })
    expect(values.ruta).toBe('/api/rutas/1')
    expect(values.boletas).toEqual(['/api/boletas/1', '/api/boletas/2'])
  })

  it('convierte fechas ISO a Date local y descarta clientMutationId/id en create', () => {
    const values = hydrateInitialValues(
      [...fields, field({ name: 'id', namedType: 'ID' })],
      { fecha: '2024-01-15T10:00:00Z', clientMutationId: 'x', id: '/api/boletos/9' },
      'create',
    )
    expect(values.fecha).toBeInstanceOf(Date)
    expect((values.fecha as Date).getFullYear()).toBe(2024)
    expect(values.clientMutationId).toBeUndefined()
    expect(values.id).toBeUndefined()
  })

  it('devuelve {} sin item', () => {
    expect(hydrateInitialValues(fields, null)).toEqual({})
  })
})

describe('serializeSubmitValue', () => {
  const fields = [
    field({ name: 'fecha', namedType: 'Date' }),
    field({ name: 'total', namedType: 'Float' }),
  ]

  it('normaliza fechas Date e ISO a YYYY-MM-DD local y pasa el resto', () => {
    const local = new Date(2024, 0, 15, 12, 0, 0)
    const out = serializeSubmitValue(fields, {
      fecha: local,
      total: 12.5,
    })
    expect(out.fecha).toBe('2024-01-15')
    expect(out.total).toBe(12.5)
  })

  it('recorta strings ISO y pasa nulls', () => {
    const out = serializeSubmitValue(fields, { fecha: '2024-02-20T23:59:59Z', total: null })
    expect(out.fecha).toBe('2024-02-20')
    expect(out.total).toBeNull()
  })
})
