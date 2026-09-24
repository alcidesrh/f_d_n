/**
 * Tipos del panel /migracion (backend: src/Controller/MigracionController.php).
 */

export interface EntidadMigracion {
  nombre: string;
  etiqueta: string;
  dependencias: string[];
  tablaFuente: string | null;
  tablasDestino: string[];
  soportaRangoFechas: boolean;
  soportaCantidad: boolean;
  totalFuente: number;
}

export interface ConteoMigracion {
  nuevo: number;
  legado: number;
}

/** Clave = entidad ("empresa", ..., "salida") o "total". */
export type IndicadoresMigracion = Record<string, ConteoMigracion>;

export type TipoJobMigracion =
  "reset" | "truncar" | "estaticos" | "entidad" | "iam" | "config" | "todo";

export type EstadoJobMigracion =
  "pending" | "running" | "done" | "cancelado" | "error" | "abortado";

export interface ParametrosJobMigracion {
  entidad: string | null;
  desde: string | null;
  hasta: string | null;
  cantidad: number | null;
  clean: boolean;
}

export interface JobMigracion {
  id: string;
  tipo: TipoJobMigracion;
  parametros: ParametrosJobMigracion;
  entidad: string | null;
  estado: EstadoJobMigracion | string;
  creado_en: string | null;
  iniciado_en: string | null;
  terminado_en: string | null;
  actualizado_en: string;
  procesados: number;
  total: number | null;
  contadores: Record<string, number>;
  errores: number;
  mensaje: string;
  duracion: number | null;
  pid?: number | null;
  solicitud_cancelacion?: boolean;
  error?: string;
}

export interface EstadoMigracion {
  ejecutando: boolean;
  actual: JobMigracion | null;
  recientes: JobMigracion[];
}

export interface LogJobMigracion {
  log: string;
  offset: number;
  fin: boolean;
}

export interface PayloadEjecutar {
  tipo: TipoJobMigracion;
  entidad?: string;
  desde?: string | null;
  hasta?: string | null;
  cantidad?: number | null;
  clean?: boolean;
}
