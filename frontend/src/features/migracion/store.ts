import { defineStore } from "pinia";
import { ref } from "vue";
import { http } from "@/core/http";
import type {
  EntidadMigracion,
  EstadoMigracion,
  IndicadoresMigracion,
  JobMigracion,
  LogJobMigracion,
  PayloadEjecutar,
} from "./types";

const INTERVALO_ESTADO_MS = 6000;
const INTERVALO_LOG_MS = 5000;
const INTERVALO_INDICADORES_MS = 8000;

/** Consultas del polling: no encienden la barra de carga. */
const silent = { silent: true } as const;
const message = (e: unknown) => (e instanceof Error ? e.message : String(e));

/**
 * Estado del panel de migración: catálogo de entidades, indicadores
 * nuevo/legado, job activo (single-flight) con su log y el historial reciente.
 *
 * El polling se arranca con `iniciarPolling()` (lo hace la página /migracion
 * al montar) y se detiene con `detenerPolling()`.
 */
export const useMigracionStore = defineStore("migracion", () => {
  const entidades = ref<EntidadMigracion[]>([]);
  const indicadores = ref<IndicadoresMigracion>({});
  const estado = ref<EstadoMigracion>({
    ejecutando: false,
    actual: null,
    recientes: [],
  });
  const log = ref("");
  const logOffset = ref(0);
  const logFin = ref(true);
  /** id del job cuyo log estamos acumulando (null = sin job activo). */
  const logJobId = ref<string | null>(null);
  const cargado = ref(false);
  const error = ref("");

  let timers: ReturnType<typeof setInterval>[] = [];
  let cargandoEstado = false;
  let cargandoLog = false;

  async function cargarEntidades(): Promise<void> {
    try {
      entidades.value = await http.get<EntidadMigracion[]>("/migracion/entidades");
    } catch (e) {
      error.value = message(e);
    }
  }

  async function cargarIndicadores(): Promise<void> {
    try {
      indicadores.value = await http.get<IndicadoresMigracion>("/migracion/indicadores", silent);
    } catch (e) {
      error.value = message(e);
    }
  }

  /** Tail incremental del log del job activo desde logOffset. */
  async function tailLog(): Promise<void> {
    if (cargandoLog) return;
    const id = logJobId.value;
    if (!id || logFin.value) return;
    cargandoLog = true;
    try {
      const chunk = await http.get<LogJobMigracion>(
        `/migracion/ejecutar/${encodeURIComponent(id)}/log?desde=${logOffset.value}`,
        silent,
      );
      if (chunk.log) log.value += chunk.log;
      if (chunk.offset > logOffset.value) logOffset.value = chunk.offset;
      logFin.value = chunk.fin;
    } catch (e) {
      error.value = message(e);
    } finally {
      cargandoLog = false;
    }
  }

  async function refrescarEstado(): Promise<void> {
    if (cargandoEstado) return;
    cargandoEstado = true;
    try {
      const nuevo = await http.get<EstadoMigracion>("/migracion/estado", silent);
      estado.value = nuevo;
      const activo = nuevo.actual;
      if (activo) {
        if (logJobId.value !== activo.id) {
          // Cambió el job en curso: reiniciar el log desde cero.
          logJobId.value = activo.id;
          log.value = "";
          logOffset.value = 0;
          logFin.value = false;
        }
        // Un primero tail inmediato evita esperar al ciclo del log.
        await tailLog();
      } else if (logJobId.value !== null) {
        // El job activo terminó: drenar el log una última vez.
        await tailLog();
        logJobId.value = null;
      }
      cargado.value = true;
    } catch (e) {
      error.value = message(e);
    } finally {
      cargandoEstado = false;
    }
  }

  async function arrancarJob(payload: PayloadEjecutar): Promise<JobMigracion> {
    const job = await http.post<JobMigracion>("/migracion/ejecutar", payload);
    await refrescarEstado();
    return job;
  }

  /** Pide cancelar el job en curso (el proceso la respeta en su próxima iteración). */
  async function cancelarActual(): Promise<void> {
    const actual = estado.value.actual;
    if (actual) await http.post(`/migracion/ejecutar/${encodeURIComponent(actual.id)}/cancelar`);
  }

  /** Relanza un job con los mismos parámetros. */
  function reejecutar(job: JobMigracion): Promise<JobMigracion> {
    const { entidad, desde, hasta, cantidad, clean } = job.parametros ?? {};
    return arrancarJob({
      tipo: job.tipo,
      ...(entidad ? { entidad } : {}),
      ...(desde ? { desde } : {}),
      ...(hasta ? { hasta } : {}),
      ...(cantidad != null ? { cantidad } : {}),
      ...(clean ? { clean: true } : {}),
    });
  }

  function iniciarPolling(): void {
    detenerPolling();
    timers.push(setInterval(() => refrescarEstado(), INTERVALO_ESTADO_MS));
    timers.push(setInterval(() => tailLog(), INTERVALO_LOG_MS));
    timers.push(setInterval(() => cargarIndicadores(), INTERVALO_INDICADORES_MS));
  }

  function detenerPolling(): void {
    timers.forEach((t) => clearInterval(t));
    timers = [];
  }

  /** Carga inicial completa (entidades + indicadores + estado) y arranca el polling. */
  async function inicializar(): Promise<void> {
    await Promise.all([cargarEntidades(), cargarIndicadores(), refrescarEstado()]);
    iniciarPolling();
  }

  return {
    entidades,
    indicadores,
    estado,
    log,
    logOffset,
    logFin,
    logJobId,
    cargado,
    error,
    cargarEntidades,
    cargarIndicadores,
    refrescarEstado,
    tailLog,
    arrancarJob,
    cancelarActual,
    reejecutar,
    iniciarPolling,
    detenerPolling,
    inicializar,
  };
});
