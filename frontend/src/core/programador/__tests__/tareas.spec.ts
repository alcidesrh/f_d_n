import { describe, expect, it } from "vitest";
import { describirResumen, duracion, nuncaCorrio, salud, type EstadoTarea } from "../tareas";

const tarea = (extra: Partial<EstadoTarea> = {}): EstadoTarea => ({
  codigo: "purgar_venta",
  nombre: "Borrar reservas web vencidas",
  cuando: "Cada minuto",
  ultimoInicio: null,
  ultimoFin: null,
  ultimoExito: null,
  duracionMs: null,
  resultado: null,
  error: null,
  resumen: null,
  corridas: 0,
  fallosSeguidos: 0,
  detenida: false,
  ...extra,
});

describe("programador de tareas", () => {
  it("la salud de una tarea", () => {
    expect(salud(tarea())).toBe("sin-correr");
    expect(salud(tarea({ ultimoExito: "2026-10-29T06:00:00-06:00", resultado: "ok" }))).toBe("bien");
    expect(salud(tarea({ ultimoExito: "2026-10-29T06:00:00-06:00", resultado: "error" }))).toBe("error");
    expect(salud(tarea({ resultado: "error", detenida: true }))).toBe("detenida");
  });

  it("sabe cuando el programador nunca corrió", () => {
    expect(nuncaCorrio([tarea(), tarea()])).toBe(true);
    expect(nuncaCorrio([tarea(), tarea({ ultimoInicio: "2026-10-29T06:00:00-06:00" })])).toBe(false);
    expect(nuncaCorrio([])).toBe(false);
  });

  it("describe el resumen y la duración", () => {
    expect(describirResumen({ reservas: 2, ventas: 0 })).toBe("reservas 2 · ventas 0");
    expect(describirResumen({ apagada: true })).toBe("apagada");
    expect(describirResumen({ resumen_diario: { enviados: 3 } })).toBe("resumen_diario: enviados 3");
    expect(describirResumen(null)).toBe("");
    expect(duracion(820)).toBe("820 ms");
    expect(duracion(12_400)).toBe("12 s");
    expect(duracion(75_300)).toBe("1 min 15 s");
  });
});
