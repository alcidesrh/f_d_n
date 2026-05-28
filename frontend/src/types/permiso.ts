import type { Item } from "./item";

export interface Permiso extends Item {
  roles?: any;
  parents?: array;
  children?: array;
  nombre?: string;
  null;
  nota?: string;
  null;
  label?: string;
  null;
  status?: string;
  null;
  id?: number;
}
