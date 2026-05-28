import type { Item } from "./item";

export interface Role extends Item {
  nombre?: string;
  parents?: array;
  children?: array;
  permisos?: any;
  actions?: any;
  label?: string;
  id?: number;
}
