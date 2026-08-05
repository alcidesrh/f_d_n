export interface Trayecto {
  "@id"?: string;
  origen?: any;
  destino?: any;
  distanciaKm?: any;
  duracionEstimadaMinutos?: any;
  activo?: any;
  legacyId?: any;
  recorridos?: string[];
  label?: any;
  readonly trayectosHijos?: any;
  readonly trayectosPadres?: any;
  readonly id?: any;
}
