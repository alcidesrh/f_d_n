/**
 * Programador de tareas (ADR-044): el latido de cada tarea (`/api/programador`,
 * `config.editar`) y las reglas puras de cómo se muestra.
 */
import { http } from "@/core/http";

export interface EstadoTarea {
  codigo: string;
  nombre: string;
  /** «Cada minuto», «Cada día a las 01:30». */
  cuando: string;
  ultimoInicio: string | null;
  ultimoFin: string | null;
  ultimoExito: string | null;
  duracionMs: number | null;
  resultado: "ok" | "error" | "omitida" | null;
  error: string | null;
  resumen: Record<string, unknown> | null;
  corridas: number;
  fallosSeguidos: number;
  /** Sin una corrida buena dentro de su tolerancia. */
  detenida: boolean;
}

export interface EstadoProgramador {
  tareas: EstadoTarea[];
  /** Trabajos en segundo plano que agotaron sus reintentos. */
  fallidos: number;
}

export const fetchProgramador = () => http.get<EstadoProgramador>("/programador/tareas");

export const ejecutarTarea = (codigo: string) => http.post<{ encolada: string }>(`/programador/tareas/${codigo}/ejecutar`, {});

export type Salud = "detenida" | "error" | "bien" | "sin-correr";

export function salud(t: EstadoTarea): Salud {
  if (t.detenida) return "detenida";
  if (t.resultado === "error") return "error";
  return t.ultimoExito ? "bien" : "sin-correr";
}

export const SALUD: Record<Salud, { etiqueta: string; severidad: "danger" | "warn" | "success" | "secondary" }> = {
  detenida: { etiqueta: "Detenida", severidad: "danger" },
  error: { etiqueta: "Falló la última", severidad: "warn" },
  bien: { etiqueta: "Al día", severidad: "success" },
  "sin-correr": { etiqueta: "Aún no corre", severidad: "secondary" },
};

/** El programador nunca corrió nada: los contenedores no están arriba (o es una instalación nueva). */
export const nuncaCorrio = (tareas: EstadoTarea[]) => tareas.length > 0 && tareas.every((t) => t.ultimoInicio === null);

/** `{ reservas: 2, ventas: 0 }` → «reservas 2 · ventas 0»; anida un nivel. */
export function describirResumen(resumen: Record<string, unknown> | null): string {
  if (!resumen) return "";
  return Object.entries(resumen)
    .map(([clave, valor]) => {
      if (valor !== null && typeof valor === "object") return `${clave}: ${describirResumen(valor as Record<string, unknown>)}`;
      if (typeof valor === "boolean") return valor ? clave : `sin ${clave}`;
      return `${clave} ${String(valor)}`;
    })
    .join(" · ");
}

/** 820 → «820 ms»; 75_300 → «1 min 15 s». */
export function duracion(ms: number | null): string {
  if (ms === null) return "";
  if (ms < 1000) return `${ms} ms`;
  const s = Math.round(ms / 1000);
  return s < 60 ? `${s} s` : `${Math.floor(s / 60)} min${s % 60 ? ` ${s % 60} s` : ""}`;
}
