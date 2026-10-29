/** "Mi cuenta → Reportes y avisos": suscripciones a los reportes programados (`MisReportesController`, ADR-044). */
import { http, request } from "@/core/http";

export interface ReporteSuscribible {
  codigo: string;
  nombre: string;
  descripcion: string;
  /** Sale cada mañana con lo del día anterior (si no, al ocurrir). */
  diario: boolean;
  /** Su alcance le permite recibirlo. */
  puede: boolean;
  /** Por qué no puede, si no puede. */
  motivo: string | null;
  suscrito: boolean;
  porChat: boolean;
  porCorreo: boolean;
}

export interface MisReportes {
  reportes: ReporteSuscribible[];
  /** El correo de su cuenta: sin él, no hay envío por correo. */
  correo: string | null;
}

export const fetchMisReportes = () => http.get<MisReportes>("/me/reportes");

/** Los dos en false: deja de recibirlo. */
export const cambiarReporte = (codigo: string, porChat: boolean, porCorreo: boolean) => request<MisReportes>(`/me/reportes/${codigo}`, { method: "PUT", body: { porChat, porCorreo } });
