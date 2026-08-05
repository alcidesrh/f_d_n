/**
 * Map of static TypeScript entity definitions derived from GraphQL Schema snapshot.
 * Provides autocompletion and strong static typing for useCollection, useItem, and useEntityMutations.
 * All domain interfaces live in `frontend/interfaces/` and are re-exported here as a single barrel.
 */

import type { Action } from '../interfaces/action';
import type { Agnostic } from '../interfaces/agnostic';
import type { ApiToken } from '../interfaces/apitoken';
import type { Asiento } from '../interfaces/asiento';
import type { Boleto } from '../interfaces/boleto';
import type { Bus } from '../interfaces/bus';
import type { BusMarca } from '../interfaces/busmarca';
import type { Cliente } from '../interfaces/cliente';
import type { CollectionFieldConfig } from '../interfaces/collectionfieldconfig';
import type { Empresa } from '../interfaces/empresa';
import type { Enclave } from '../interfaces/enclave';
import type { Estacion } from '../interfaces/estacion';
import type { Factura } from '../interfaces/factura';
import type { FormFieldConfig } from '../interfaces/formfieldconfig';
import type { Icon } from '../interfaces/icon';
import type { LayoutProfile } from '../interfaces/layoutprofile';
import type { LayoutProfileRole } from '../interfaces/layoutprofilerole';
import type { LayoutProfileUsuario } from '../interfaces/layoutprofileusuario';
import type { LayoutSchema } from '../interfaces/layoutschema';
import type { LayoutSchemaItem } from '../interfaces/layoutschemaitem';
import type { Localidad } from '../interfaces/localidad';
import type { Nacion } from '../interfaces/nacion';
import type { Parada } from '../interfaces/parada';
import type { Permiso } from '../interfaces/permiso';
import type { Piloto } from '../interfaces/piloto';
import type { Recorrido } from '../interfaces/recorrido';
import type { RecorridoMatrioska } from '../interfaces/recorridomatrioska';
import type { Role } from '../interfaces/role';
import type { Servicio } from '../interfaces/servicio';
import type { Status } from '../interfaces/status';
import type { Trayecto } from '../interfaces/trayecto';
import type { Usuario } from '../interfaces/usuario';
import type { Venta } from '../interfaces/venta';
import type { VueRoute } from '../interfaces/vueroute';

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
