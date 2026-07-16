export interface Action {
  "@id"?: string;
  ruta?: any;
  nombre?: any;
  codigo?: any;
  recurso?: any;
  operacion?: any;
  grupo?: any;
  roles?: string[];
  permisos?: string[];
  label?: any;
  status?: string;
  readonly id?: any;
}
