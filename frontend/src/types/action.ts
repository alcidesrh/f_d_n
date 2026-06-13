import type { Item } from "./item";

export interface Action extends Item {
  codigo?: string;
  nombre?: string;
  recurso?: string;
  operacion?: string;
  grupo?: string;
  ruta?: string;
  roles?: any[];
  permisos?: any[];
  label?: string;
  id?: number;
}
