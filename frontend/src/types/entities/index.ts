/**
 * Contratos estáticos de entidades GraphQL derivados del snapshot de schema
 * (public/schema.graphql). Agrupados por afinidad de dominio en flota/,
 * geografia/, ventas/, seguridad/, configuracion/ y core/. Proporcionan
 * autocompletado para useCollection, useItem y useEntityMutations.
 */

import type { Action } from './seguridad';
import type { Agnostic } from './configuracion';
import type { ApiToken } from './seguridad';
import type { Asiento } from './ventas';
import type { Boleto } from './ventas';
import type { Bus } from './flota';
import type { BusMarca } from './flota';
import type { Cliente } from './ventas';
import type { CollectionFieldConfig } from './configuracion';
import type { Empresa } from './core';
import type { Enclave } from './geografia';
import type { Estacion } from './geografia';
import type { Factura } from './ventas';
import type { FormFieldConfig } from './configuracion';
import type { Icon } from './configuracion';
import type { LayoutProfile } from './configuracion';
import type { LayoutProfileRole } from './configuracion';
import type { LayoutProfileUsuario } from './configuracion';
import type { LayoutSchema } from './configuracion';
import type { LayoutSchemaItem } from './configuracion';
import type { Localidad } from './geografia';
import type { Nacion } from './geografia';
import type { Parada } from './geografia';
import type { Permiso } from './seguridad';
import type { Piloto } from './flota';
import type { Recorrido } from './flota';
import type { RecorridoMatrioska } from './flota';
import type { Role } from './seguridad';
import type { Servicio } from './flota';
import type { Status } from './core';
import type { Trayecto } from './flota';
import type { Usuario } from './seguridad';
import type { Venta } from './ventas';
import type { VueRoute } from './configuracion';

export interface EntityMap {
  Action: Action;
  Agnostic: Agnostic;
  ApiToken: ApiToken;
  Asiento: Asiento;
  Boleto: Boleto;
  Bus: Bus;
  BusMarca: BusMarca;
  Cliente: Cliente;
  CollectionFieldConfig: CollectionFieldConfig;
  Empresa: Empresa;
  Enclave: Enclave;
  Estacion: Estacion;
  Factura: Factura;
  FormFieldConfig: FormFieldConfig;
  Icon: Icon;
  LayoutProfile: LayoutProfile;
  LayoutProfileRole: LayoutProfileRole;
  LayoutProfileUsuario: LayoutProfileUsuario;
  LayoutSchema: LayoutSchema;
  LayoutSchemaItem: LayoutSchemaItem;
  Localidad: Localidad;
  Nacion: Nacion;
  Parada: Parada;
  Permiso: Permiso;
  Piloto: Piloto;
  Recorrido: Recorrido;
  RecorridoMatrioska: RecorridoMatrioska;
  Role: Role;
  Servicio: Servicio;
  Status: Status;
  Trayecto: Trayecto;
  Usuario: Usuario;
  Venta: Venta;
  VueRoute: VueRoute;
}

export type {
  Action,
  Agnostic,
  ApiToken,
  Asiento,
  Boleto,
  Bus,
  BusMarca,
  Cliente,
  CollectionFieldConfig,
  Empresa,
  Enclave,
  Estacion,
  Factura,
  FormFieldConfig,
  Icon,
  LayoutProfile,
  LayoutProfileRole,
  LayoutProfileUsuario,
  LayoutSchema,
  LayoutSchemaItem,
  Localidad,
  Nacion,
  Parada,
  Permiso,
  Piloto,
  Recorrido,
  RecorridoMatrioska,
  Role,
  Servicio,
  Status,
  Trayecto,
  Usuario,
  Venta,
  VueRoute,
};
