/** Fechas y duraciones del programador, en español de Guatemala. */
import { deDia } from '@/core/salida/filtro'

export const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { day: '2-digit', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))

/** `AAAA-MM-DD` → «20 mar». */
export const diaCorto = (d: string) => new Intl.DateTimeFormat('es-GT', { day: 'numeric', month: 'short' }).format(deDia(d))

/** `AAAA-MM-DD` → «sáb, 20 mar 2027». */
export const diaLargo = (d: string) => new Intl.DateTimeFormat('es-GT', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }).format(deDia(d))

/** 290 → «4 h 50 min». */
export const duracion = (min: number) => (min >= 60 ? `${Math.floor(min / 60)} h${min % 60 ? ` ${min % 60} min` : ''}` : `${min} min`)
