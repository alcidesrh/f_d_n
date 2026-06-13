import type { Item } from "./item";

export interface User extends Item {
  username?: string;
  password?: string;
  plainPassword?: string;
  apiTokens?: any[];
  userRoles?: any[];
  permisos?: any;
  directActions?: any[];
  deniedActions?: any[];
  label?: string;
  createdAt?: string;
  updatedAt?: string;
  status?: string;
  legacyId?: number;
  apellido?: string;
  nombre?: string;
  email?: string;
  nit?: string;
  telefono?: string;
  direccion?: string;
  localidad?: any;
  fullName?: string;
  id?: number;
  userIdentifier?: string;
  roles?: any;
  token?: string;
  validTokenStrings?: string;
}
