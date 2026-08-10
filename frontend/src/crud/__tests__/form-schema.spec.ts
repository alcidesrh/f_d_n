import { describe, it, expect } from 'vitest';
import { buildSchema } from 'graphql';
import type { FormKitSchemaNode } from '@formkit/core';
import { buildFormSchema, isRelationNode } from '../form-schema';

const SDL = `
  enum EstadoCivil { SOLTERO, CASADO }

  type Empresa { id: ID!, nombre: String! }
  type Status { id: ID!, nombre: String! }
  type Trayecto { id: ID! }

  type Piloto {
    id: ID!
    nombre: String!
    apellido: String
    edad: Int!
    activo: Boolean
    nacimiento: DateTime
    estadoCivil: EstadoCivil
    empresa: Empresa
    status: Status
    trayectos: [Trayecto!]!
    plainPassword: String
    _id: Int!
  }

  input DireccionInput { calle: String!, ciudad: String }

  input createPilotoInput {
    nombre: String!
    apellido: String
    edad: Int!
    activo: Boolean
    nacimiento: DateTime
    estadoCivil: EstadoCivil
    empresa: String
    status: String
    trayectos: [String]
    direccion: DireccionInput
    plainPassword: String
    clientMutationId: String
  }

  input updatePilotoInput {
    id: ID!
    nombre: String
    apellido: String
    clientMutationId: String
  }

  type createPilotoPayload { piloto: Piloto, clientMutationId: String }
  type updatePilotoPayload { piloto: Piloto, clientMutationId: String }

  scalar DateTime

  type PaginationInfo { totalCount: Int! }
  type PilotoPageConnection { collection: [Piloto!]!, paginationInfo: PaginationInfo! }

  type Query {
    piloto(id: ID!): Piloto
    pilotos(page: Int, itemsPerPage: Int): PilotoPageConnection
  }

  type Mutation {
    createPiloto(input: createPilotoInput!): createPilotoPayload
    updatePiloto(input: updatePilotoInput!): updatePilotoPayload
  }
`;

describe('buildFormSchema', () => {
  const schema = buildSchema(SDL);
  const byName = (nodes: unknown[]) =>
    new Map((nodes as Array<Record<string, unknown>>).map((n) => [String(n.name), n as { name: string } & Record<string, unknown>]));
  const buildNodes = (mode: 'create' | 'update') => buildFormSchema(schema, 'Piloto', mode) as unknown as Array<{ name: string } & Record<string, unknown>>;

  it('genera nodos por tipo de campo en modo create', () => {
    const map = byName(buildNodes('create'));

    expect(map.get('nombre')).toMatchObject({ $formkit: 'InputText', validation: 'required' });
    expect(map.get('apellido')).toMatchObject({ $formkit: 'InputText' });
    expect(map.get('apellido')!.validation).toBeUndefined();
    expect(map.get('edad')).toMatchObject({ $formkit: 'InputNumber', validation: 'required' });
    expect(map.get('activo')).toMatchObject({ $formkit: 'ToggleSwitch' });
    expect(map.get('nacimiento')).toMatchObject({ $formkit: 'DatePicker' });
  });

  it('resuelve enums a Select con opciones del schema', () => {
    const node = byName(buildNodes('create')).get('estadoCivil');
    expect(node).toMatchObject({ $formkit: 'Select', options: [{ label: 'SOLTERO', value: 'SOLTERO' }, { label: 'CASADO', value: 'CASADO' }] });
  });

  it('resuelve relaciones (IRIs de texto) a Select/MultiSelect con marker', () => {
    const map = byName(buildNodes('create'));

    const empresa = map.get('empresa');
    expect(empresa).toMatchObject({ $formkit: 'Select', relation: true, typeName: 'Empresa' });
    expect(isRelationNode(empresa as unknown as FormKitSchemaNode)).toBe(true);

    const status = map.get('status');
    expect(status).toMatchObject({ $formkit: 'Select', relation: true, typeName: 'Status' });

    const trayectos = map.get('trayectos');
    expect(trayectos).toMatchObject({ $formkit: 'MultiSelect', relation: true, typeName: 'Trayecto' });
  });

  it('anida input-object como group recursivo', () => {
    const direccion = byName(buildNodes('create')).get('direccion');
    expect(direccion).toMatchObject({ $formkit: 'group' });
    const children = byName(direccion!.children as unknown[]);
    expect(children.get('calle')).toMatchObject({ $formkit: 'InputText', validation: 'required' });
    expect(children.get('ciudad')).toMatchObject({ $formkit: 'InputText' });
  });

  it('excluye id, clientMutationId y campos sensibles', () => {
    const names = buildNodes('create').map((n) => n.name);
    expect(names).not.toContain('clientMutationId');
    expect(names).not.toContain('plainPassword');
  });

  it('modo update omite required e id', () => {
    const map = byName(buildNodes('update'));
    expect(map.get('nombre')!.validation).toBeUndefined();
    expect(map.has('id')).toBe(false);
  });

  it('devuelve [] si la entidad no tiene mutación', () => {
    const sdl = buildSchema(
      `type Query { piloto(id: ID!): Piloto, pilotos(page: Int, itemsPerPage: Int): PilotoPageConnection }
      type Piloto { id: ID!, nombre: String! }
      type PaginationInfo { totalCount: Int! }
      type PilotoPageConnection { collection: [Piloto!]!, paginationInfo: PaginationInfo! }`,
    );
    expect(buildFormSchema(sdl, 'Piloto', 'create')).toEqual([]);
    expect(buildFormSchema(sdl, 'Piloto', 'update')).toEqual([]);
  });
});
