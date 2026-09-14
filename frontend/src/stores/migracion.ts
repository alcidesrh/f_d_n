import { defineStore } from "pinia";
import { ref } from "vue";
import { useUserSessionStore } from "@/stores/session";
import { manejarNoAutorizado } from "@/lib/manejar401";
import type {
  EntidadMigracion,
  EstadoMigracion,
  IndicadoresMigracion,
  JobMigracion,
  LogJobMigracion,
  PayloadEjecutar,
} from "@/types/migracion";

const API_BASE = import.meta.env.VITE_REST_ENDPOINT ?? "http://localhost/api";

const INTERVALO_ESTADO_MS = 6000;
const INTERVALO_LOG_MS = 5000;
const INTERVALO_INDICADORES_MS = 8000;

async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const session = useUserSessionStore();
  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      ...init?.headers,
      ...(init?.body ? { "Content-Type": "application/json" } : {}),
      ...(session.token ? { Authorization: `Bearer ${session.token}` } : {}),
    },
  });
  const texto = await res.text();
  let data: unknown = null;
  if (texto) {
    try {
      data = JSON.parse(texto);
    } catch {
      data = texto;
    }
  }
  if (res.status === 401) {
    // El firewall responde 401 cuando el Bearer token ya no existe/expiro en
    // api_token (p. ej. sesion de antes de un reset de BD): limpiar y al login.
    manejarNoAutorizado();
  }
  if (!res.ok) {
    const msg =
      data && typeof data === "object" && "error" in (data as object)
        ? String((data as { error: unknown }).error)
        : `HTTP ${res.status}`;
    throw new Error(msg);
  }
  return data as T;
}

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
      entidades.value = await apiFetch<EntidadMigracion[]>("/migracion/entidades");
    } catch (e) {
      error.value = e instanceof Error ? e.message : String(e);
    }
  }

  async function cargarIndicadores(): Promise<void> {
    try {
      indicadores.value = await apiFetch<IndicadoresMigracion>("/migracion/indicadores");
    } catch (e) {
      error.value = e instanceof Error ? e.message : String(e);
    }
  }

  /** Tail incremental del log del job activo desde logOffset. */
  async function tailLog(): Promise<void> {
    if (cargandoLog) return;
    const id = logJobId.value;
    if (!id || logFin.value) return;
    cargandoLog = true;
    try {
      const chunk = await apiFetch<LogJobMigracion>(
        `/migracion/ejecutar/${encodeURIComponent(id)}/log?desde=${logOffset.value}`,
      );
      if (chunk.log) log.value += chunk.log;
      if (chunk.offset > logOffset.value) logOffset.value = chunk.offset;
      logFin.value = chunk.fin;
    } catch (e) {
      error.value = e instanceof Error ? e.message : String(e);
    } finally {
      cargandoLog = false;
    }
  }

  async function refrescarEstado(): Promise<void> {
    if (cargandoEstado) return;
    cargandoEstado = true;
    try {
      const nuevo = await apiFetch<EstadoMigracion>("/migracion/estado");
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
      error.value = e instanceof Error ? e.message : String(e);
    } finally {
      cargandoEstado = false;
    }
  }

  async function arrancarJob(payload: PayloadEjecutar): Promise<JobMigracion> {
    const job = await apiFetch<JobMigracion>("/migracion/ejecutar", {
      method: "POST",
      body: JSON.stringify(payload),
    });
    await refrescarEstado();
    return job;
  }

  async function cancelarJob(id: string): Promise<void> {
    await apiFetch<JobMigracion>(`/migracion/ejecutar/${encodeURIComponent(id)}/cancelar`, {
      method: "POST",
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
    cancelarJob,
    iniciarPolling,
    detenerPolling,
    inicializar,
  };
});
