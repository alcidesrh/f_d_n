import type { Item } from "./item";

export interface User extends Item {
  username?: string;
  password?: string;
  null;
  plainPassword?: string;
  null;
  apiTokens?: array;
  userRoles?: array;
  permisos?: any;
  label?: string;
  null;
  createdAt?: string;
  updatedAt?: string;
  status?: string;
  null;
  legacyId?: integer;
  null;
  apellido?: string;
  null;
  nombre?: string;
  email?: string;
  null;
  nit?: string;
  null;
  telefono?: string;
  null;
  direccion?: string;
  null;
  localidad?: any;
  fullName?: string;
  null;
  id?: number;
  userIdentifier?: string;
  roles?: any;
  token?: string;
  validTokenStrings?: string;
}
