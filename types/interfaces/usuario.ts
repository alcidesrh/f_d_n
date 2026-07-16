export interface Usuario {
  "@id"?: string;
  username?: any;
  plainPassword: any;
  apiTokens?: string[];
  userRoles?: any;
  permisos?: string[];
  directActions?: any;
  deniedActions?: any;
  ventas?: string[];
  nombre?: any;
  apellido?: any;
  email?: any;
  nit?: any;
  telefono?: any;
  direccion?: any;
  localidad?: string;
  label?: any;
  createdAt?: any;
  updatedAt?: any;
  status?: string;
  readonly fullName?: any;
  readonly id?: any;
  readonly token?: any;
}
