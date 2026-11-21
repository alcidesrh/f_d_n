/**
 * Estado de la pantalla de venta (taquilla y agencias, ADR-021): filtros,
 * salida elegido, tramo, ocupación en vivo (Mercure), selección de
 * asientos, cliente y cotización. La lógica pura está en `core/venta/modelo`.
 *
 * La selección se arma en varias salidas: cada salida y tramo con asientos
 * elegidos es un grupo, que se conserva al cambiar de salida, fecha o
 * estaciones. Al cobrar, cada grupo es una venta (`BoletoVenta`) con su
 * factura y su ticket: compras separadas del mismo cliente.
 */
import { defineStore } from "pinia";
import { computed, onScopeDispose, ref, shallowRef, watch } from "vue";
import * as api from "@/core/venta/api";
import { alternar, bajadaPorDefecto, clasesDelTrayecto, depurarSeleccion, diaISO, estadoEnMapa, fecha as fechaDe, hora as horaDe, nuevoToken, subidaPorDefecto, sumarImportes, trayectoEntre } from "@/core/venta/modelo";
import { suscribir } from "@/core/realtime";
import { notify } from "@/core/notify";
import { useAutorizacionesStore } from "@/core/autorizacion/store";
import { useAccesoStore } from "@/core/acceso/store";
import type { AsientoCroquis } from "@/core/croquis/types";
import { emparejar, listaParaReasignar, motivoNoOperable, salidasReasignables, tramoDelBoleto } from "@/core/venta/reasignacion";
import type { AsientoOcupado, BoletoOperable, Cliente, Comprobante, ContextoVenta, Cotizacion, ErrorVenta, Importe, SalidaDetalle, SalidaResumen } from "@/core/venta/types";

export interface OpcionesCobro {
  tipoPago: number | null;
  moneda: number | null;
  enviarCorreo: boolean;
  cortesia: boolean;
  sinFacturaElectronica?: boolean;
}

/** Asientos elegidos de una salida en un tramo: al cobrar, una venta. */
export interface GrupoVenta {
  /** `salida/trayecto`. */
  clave: string;
  salidaId: number;
  trayectoId: number;
  /** Clave de idempotencia de su venta: la misma en los reintentos. */
  token: string;
  asientos: number[];
  /** Número en el croquis de cada asiento elegido. */
  numeros: Record<number, number>;
  /** Pasajero por asiento (si no, viaja el cliente de la venta). */
  pasajeros: Record<number, Cliente | null>;
  cobrarTrayectoCompleto: boolean;
  cotizacion: Cotizacion | null;
  errorCotizacion: string;
  /** Para la lista de lo elegido: hora donde sube, «Sube → Baja» y empresa. */
  fecha: string;
  ruta: string;
  empresa: string | null;
  /** Tópico Mercure de su ocupación. */
  topico: string;
}

const dos = (n: number) => String(n).padStart(2, "0");
const inicioDeDia = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0);
const finDeDia = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59);
/** "AAAA-MM-DDTHH:MM" en hora local, como lo espera `/venta/salidas`. */
const localISO = (d: Date) => `${diaISO(d)}T${dos(d.getHours())}:${dos(d.getMinutes())}`;
/** `27/09/2026 08:00 p. m.`: cuándo sale el viaje de un grupo. */
export const fechaCorta = (iso: string) => `${fechaDe(iso)} ${horaDe(iso)}`;

export const useVentaStore = defineStore("venta", () => {
  const contexto = shallowRef<ContextoVenta | null>(null);
  const errorCarga = ref("");

  const fecha = ref(new Date());
  const estacionId = ref<number | null>(null);
  const salidas = ref<SalidaResumen[]>([]);
  const cargandoSalidas = ref(false);
  /** Filtro por empresas (en el cliente, sobre las salidas ya cargadas); vacío = todas. */
  const empresaIds = ref<number[]>([]);
  const empresas = computed(() => {
    const porId = new Map<number, string>();
    for (const r of salidas.value) if (r.empresa) porId.set(r.empresa.id, r.empresa.nombre);
    return [...porId].map(([id, nombre]) => ({ id, nombre })).sort((a, b) => a.nombre.localeCompare(b.nombre));
  });
  /** Destino (estación): lo resuelve el servidor, igual que el origen. */
  const destinoId = ref<number | null>(null);
  /** Buscar por rango de fecha y hora en vez de un solo día. */
  const porRango = ref(false);
  const desde = ref(inicioDeDia(new Date()));
  const hasta = ref(finDeDia(new Date()));
  /**
   * Reasignando: los boletos que se pasan a otro asiento o salida (todos de una
   * venta) y los filtros "Sube en"/"Baja en" que había, que se reponen al terminar; null = venta normal.
   */
  const reasignacion = shallowRef<{ boletos: BoletoOperable[]; ventaId: number; filtros: { estacionId: number | null; destinoId: number | null } } | null>(null);
  /** El comprobante que se muestra es de una reasignación (no de una venta). */
  const comprobanteReasignado = ref(false);
  /** Lo que se ofrece de una lista de salidas: sin canceladas, con el filtro de empresas y, reasignando, solo las que admiten los boletos. */
  function visibles(lista: SalidaResumen[]): SalidaResumen[] {
    const filtradas = lista.filter((r) => r.estado !== "cancelada" && (empresaIds.value.length === 0 || (r.empresa != null && empresaIds.value.includes(r.empresa.id))));
    return reasignacion.value ? salidasReasignables(filtradas, reasignacion.value.boletos, new Date()) : filtradas;
  }
  const salidasVisibles = computed(() => visibles(salidas.value));

  const salidaId = ref<number | null>(null);
  const detalle = shallowRef<SalidaDetalle | null>(null);
  const cargandoDetalle = ref(false);
  /** Tramo del viaje: el de la fila elegida, que resuelve el servidor con los filtros "Sube en" y "Baja en". */
  const sube = ref<number | null>(null);
  const baja = ref<number | null>(null);
  const ocupados = ref<AsientoOcupado[]>([]);
  /**
   * Asiento cuyo detalle se abre cuando entre el croquis (se llegó desde un asiento
   * relacionado de otra salida). Vive aquí y no en la pantalla: cambiar la URL la vuelve a montar.
   */
  const asientoPorAbrir = ref<number | null>(null);

  /** Lo elegido, en el orden en que se eligió: un grupo por salida y tramo. */
  const grupos = ref<GrupoVenta[]>([]);
  const cliente = ref<Cliente | null>(null);
  const observacion = ref("");

  const vendiendo = ref(false);
  const falloFacturacion = ref<ErrorVenta | null>(null);
  /** Comprobantes de la venta (uno por salida) o el de una reasignación. */
  const comprobantes = shallowRef<Comprobante[]>([]);

  let desuscribir: (() => void) | null = null;
  // Al descartar la store (cambio de usuario) deja de oír la ocupación.
  onScopeDispose(() => desuscribir?.());

  const trayectoId = computed(() => (detalle.value ? trayectoEntre(detalle.value, sube.value, baja.value) : null));
  const claveActual = computed(() => (salidaId.value && trayectoId.value != null ? `${salidaId.value}/${trayectoId.value}` : null));
  /** El grupo de la salida y el tramo abiertos (null si aún no tiene asientos). */
  const grupoActual = computed(() => grupos.value.find((g) => g.clave === claveActual.value) ?? null);
  const grupo = (clave: string) => grupos.value.find((g) => g.clave === clave) ?? null;
  /** Asientos elegidos en la salida abierta (los del croquis). */
  const seleccion = computed<number[]>({
    get: () => grupoActual.value?.asientos ?? [],
    set: (asientos) => fijarAsientos(asientos),
  });
  const pasajeros = computed(() => grupoActual.value?.pasajeros ?? {});
  const cotizacion = computed(() => grupoActual.value?.cotizacion ?? null);
  /** «Cobrar el trayecto completo» de la salida abierta; sin asientos aún, lo que tendrá su grupo. */
  const cobrarCompletoSinGrupo = ref(false);
  const cobrarTrayectoCompleto = computed<boolean>({
    get: () => grupoActual.value?.cobrarTrayectoCompleto ?? cobrarCompletoSinGrupo.value,
    set: (v) => {
      if (grupoActual.value) grupoActual.value.cobrarTrayectoCompleto = v;
      else cobrarCompletoSinGrupo.value = v;
    },
  });
  /** El primer error de tarifa; de otra salida, con su viaje delante. */
  const errorCotizacion = computed(() => {
    const g = grupos.value.find((x) => x.errorCotizacion);
    if (!g) return "";
    return g.clave === claveActual.value || grupos.value.length === 1 ? g.errorCotizacion : `${g.ruta}: ${g.errorCotizacion}`;
  });
  const totalAsientos = computed(() => grupos.value.reduce((n, g) => n + g.asientos.length, 0));
  /** Hay asientos elegidos en más de una salida (o tramo). */
  const variasSalidas = computed(() => grupos.value.length > 1);
  /** Se viaja un tramo intermedio de la ruta (no de la primera a la última parada). */
  const esTramoIntermedio = computed(() => !!detalle.value && !detalle.value.trayectos.find((t) => t.id === trayectoId.value)?.completo);
  /** Cobrar el trayecto completo solo si se viaja un tramo intermedio y el completo tiene tarifa. */
  const puedeCobrarCompleto = computed(() => esTramoIntermedio.value && !!detalle.value?.trayectos.some((t) => t.completo));
  /** Clases de asiento con tarifa en el tramo elegido: las demás se ven bloqueadas. */
  const clasesVendibles = computed(() => (detalle.value ? clasesDelTrayecto(detalle.value, trayectoId.value) : []));
  /** Motivo por el que el salida ya no se vende (p. ej. de un día pasado): el croquis queda de solo lectura. */
  const noVendible = computed(() => detalle.value?.noVendible ?? null);
  /** Libres que conviene vender para el tramo (no fragmentan el bus, ADR-030). */
  const recomendados = shallowRef<AsientoOcupado[]>([]);
  const estadoAsiento = computed(() => estadoEnMapa(ocupados.value, seleccion.value, noVendible.value ? [] : clasesVendibles.value, noVendible.value ? [] : recomendados.value));
  const asientosCroquis = computed(() => detalle.value?.croquis.filter((e): e is AsientoCroquis => e.tipo === "asiento") ?? []);
  const libres = computed(() => asientosCroquis.value.length - ocupados.value.filter((o) => o.estado !== "propio").length);
  const preciosCotizados = computed(() => new Map<number, Importe>((cotizacion.value?.asientos ?? []).map((a) => [a.asiento, a.precio])));
  /** Reasignando: cada boleto con el asiento que se le eligió y si cuesta lo mismo. */
  const parejas = computed(() => (reasignacion.value ? emparejar(reasignacion.value.boletos, seleccion.value, preciosCotizados.value) : []));
  const puedeReasignar = computed(() => !!reasignacion.value && !!detalle.value && !noVendible.value && trayectoId.value != null && listaParaReasignar(parejas.value) && !vendiendo.value);
  /** Lo que se cobra: la suma de las ventas de todas las salidas. */
  const total = computed<Importe | null>(() => sumarImportes(grupos.value.map((g) => g.cotizacion?.total ?? null)));

  const puedeVender = computed(() => !!cliente.value && grupos.value.length > 0 && grupos.value.every((g) => !!g.cotizacion && !g.errorCotizacion) && !vendiendo.value);

  async function iniciar() {
    errorCarga.value = "";
    try {
      contexto.value = await api.fetchContexto();
      estacionId.value ??= contexto.value.estacion?.id ?? null;
      await cargarSalidas();
    } catch (e) {
      errorCarga.value = e instanceof Error ? e.message : String(e);
    }
  }

  async function refrescarContexto() {
    try {
      contexto.value = await api.fetchContexto();
    } catch {
      // El saldo de la agencia se verá desactualizado hasta el próximo refresco.
    }
  }

  async function cargarSalidas() {
    cargandoSalidas.value = true;
    try {
      const cuando = porRango.value ? { desde: localISO(desde.value), hasta: localISO(hasta.value) } : { fecha: diaISO(fecha.value) };
      salidas.value = await api.fetchSalidas(cuando, estacionId.value, destinoId.value);
      empresaIds.value = empresaIds.value.filter((id) => salidas.value.some((r) => r.empresa?.id === id));
      if (salidaId.value && !salidas.value.some((r) => r.id === salidaId.value)) {
        cerrarSalida();
      }
    } catch (e) {
      salidas.value = [];
      notify.error(api.errorVenta(e)?.error ?? "No se pudieron cargar los salidas.");
    } finally {
      cargandoSalidas.value = false;
    }
  }

  /**
   * Cambio de origen o destino: la salida abierta pertenece al filtro anterior,
   * así que se cierra (lista sin marca, croquis vacío) antes de recargar.
   */
  async function cambiarFiltroEstacion() {
    cerrarSalida();
    await cargarSalidas();
  }

  /**
   * Cambio de estación de trabajo (cabecera): «Sube en» toma la nueva, como al
   * entrar a la pantalla, y «Baja en» vuelve a Todas. Reasignando, los filtros del
   * viaje del boleto se quedan; lo nuevo es lo que se repone al terminar.
   */
  async function alCambiarEstacion() {
    // La estación de trabajo (lugar de emisión) viene del contexto: se relee antes de usarla.
    await refrescarContexto();
    const estacion = contexto.value?.estacion?.id ?? null;
    if (reasignacion.value) {
      reasignacion.value = { ...reasignacion.value, filtros: { estacionId: estacion, destinoId: null } };
      return;
    }
    estacionId.value = estacion;
    destinoId.value = null;
    await cambiarFiltroEstacion();
  }
  // Se cambia desde la cabecera, también con otra pantalla abierta: al volver a la venta ya está.
  const acceso = useAccesoStore();
  watch(
    () => acceso.estacionTrabajo?.id,
    () => void alCambiarEstacion(),
  );

  async function elegirSalida(id: number) {
    if (id === salidaId.value && detalle.value) return;
    cerrarSalida();
    salidaId.value = id;
    cargandoDetalle.value = true;
    try {
      const d = await api.fetchSalida(id);
      if (salidaId.value !== id) return;
      detalle.value = d;
      // Reasignando: el mismo tramo que tenía el boleto, si la salida lo ofrece;
      // si no, el viaje de la fila (o, sin él, la estación hasta el final).
      const viaje = salidas.value.find((r) => r.id === id)?.viaje;
      const tramo = (reasignacion.value ? tramoDelBoleto(d, reasignacion.value.boletos[0]!) : null) ?? (viaje && trayectoEntre(d, viaje.sube.id, viaje.baja.id) != null ? { sube: viaje.sube.id, baja: viaje.baja.id } : null);
      sube.value = tramo?.sube ?? subidaPorDefecto(d, estacionId.value);
      baja.value = tramo?.baja ?? bajadaPorDefecto(d, sube.value);
      await cargarOcupacion();
      desuscribir = suscribir(d.topico, () => void refrescarOcupacion());
    } catch (e) {
      notify.error(api.errorVenta(e)?.error ?? "No se pudo abrir el salida.");
      cerrarSalida();
    } finally {
      cargandoDetalle.value = false;
    }
  }

  function cerrarSalida() {
    desuscribir?.();
    desuscribir = null;
    salidaId.value = null;
    detalle.value = null;
    ocupados.value = [];
    recomendados.value = [];
    cobrarCompletoSinGrupo.value = false;
    // Lo elegido se conserva al pasar a otra salida; reasignando, solo cuenta la salida abierta.
    if (reasignacion.value) grupos.value = [];
  }

  async function cargarOcupacion(silenciosa = false) {
    if (!salidaId.value || trayectoId.value == null) return;
    const r = await api.fetchOcupacion(salidaId.value, trayectoId.value, silenciosa);
    ocupados.value = r.asientos;
    recomendados.value = r.recomendados ?? [];
  }

  /** Hay una venta o reasignación de esta pantalla esperando respuesta. */
  let enviando = false;

  /**
   * Refresco en vivo: si otro vendió un asiento elegido, se avisa y se quita.
   * El aviso de la propia venta puede llegar antes que su respuesta: esos
   * asientos los tomó esta pantalla, no otro vendedor.
   */
  async function refrescarOcupacion() {
    const durante = enviando;
    try {
      await cargarOcupacion(true);
    } catch {
      return;
    }
    if (durante || enviando) return;
    const depurada = depurarSeleccion(seleccion.value, ocupados.value);
    if (depurada.length !== seleccion.value.length) {
      notify.warning("Otro vendedor tomó un asiento que tenía elegido: se quitó de la selección.");
      seleccion.value = depurada;
    }
  }

  /**
   * Las otras salidas con asientos elegidos también se oyen: si otro vendedor
   * toma uno, se quita de su grupo (la salida abierta la oye `refrescarOcupacion`).
   */
  const vigiladas = new Map<number, () => void>();
  watch(
    () => [...new Map(grupos.value.filter((g) => g.salidaId !== salidaId.value).map((g) => [g.salidaId, g.topico]))],
    (otras) => {
      const ids = new Set(otras.map(([id]) => id));
      for (const [id, dejar] of vigiladas) {
        if (ids.has(id)) continue;
        dejar();
        vigiladas.delete(id);
      }
      for (const [id, topico] of otras) {
        if (!vigiladas.has(id)) vigiladas.set(id, suscribir(topico, () => void depurarOtrasSalidas(id)));
      }
    },
  );
  onScopeDispose(() => {
    for (const dejar of vigiladas.values()) dejar();
    vigiladas.clear();
  });

  async function depurarOtrasSalidas(salida: number) {
    for (const g of grupos.value.filter((x) => x.salidaId === salida && x.clave !== claveActual.value)) {
      if (enviando) return;
      let ocupadosDelGrupo: AsientoOcupado[];
      try {
        ocupadosDelGrupo = (await api.fetchOcupacion(g.salidaId, g.trayectoId, true)).asientos;
      } catch {
        continue;
      }
      if (enviando) return;
      const vigente = grupo(g.clave);
      if (!vigente) continue;
      const depurada = depurarSeleccion(vigente.asientos, ocupadosDelGrupo);
      if (depurada.length === vigente.asientos.length) continue;
      notify.warning(`Otro vendedor tomó un asiento que tenía elegido en ${vigente.ruta}: se quitó de la selección.`);
      fijarAsientosDe(vigente.clave, depurada);
    }
  }

  /** Cambia los asientos de la salida abierta: crea su grupo con el primero y lo quita sin ninguno. */
  function fijarAsientos(asientos: number[]) {
    const clave = claveActual.value;
    if (!clave || !detalle.value || !salidaId.value || trayectoId.value == null) return;
    if (!grupo(clave)) {
      if (!asientos.length) return;
      const d = detalle.value;
      const nombre = (id: number | null) => d.paradas.find((p) => p.id === id)?.nombre ?? "—";
      grupos.value.push({
        clave,
        salidaId: salidaId.value,
        trayectoId: trayectoId.value,
        token: nuevoToken(),
        asientos: [],
        numeros: {},
        pasajeros: {},
        cobrarTrayectoCompleto: cobrarCompletoSinGrupo.value,
        cotizacion: null,
        errorCotizacion: "",
        fecha: d.paradas.find((p) => p.id === sube.value)?.hora ?? salidas.value.find((r) => r.id === d.id)?.viaje?.sube.hora ?? d.salida,
        ruta: `${nombre(sube.value)} → ${nombre(baja.value)}`,
        empresa: d.empresa?.nombre ?? null,
        topico: d.topico,
      });
    }
    const g = grupo(clave)!;
    for (const id of asientos) {
      g.numeros[id] ??= asientosCroquis.value.find((a) => a.id === id)?.numero ?? id;
    }
    fijarAsientosDe(clave, asientos);
  }

  /** Cambia los asientos de un grupo, sin pasajeros de los que se quitaron; sin ninguno, el grupo sale. */
  function fijarAsientosDe(clave: string, asientos: number[]) {
    const g = grupo(clave);
    if (!g) return;
    if (!asientos.length) {
      grupos.value = grupos.value.filter((x) => x.clave !== clave);
      cotizando.delete(clave);
      return;
    }
    for (const id of g.asientos) {
      if (!asientos.includes(id)) delete g.pasajeros[id];
    }
    g.asientos = asientos;
    void cotizarGrupo(clave);
  }

  /** Quita un asiento de lo elegido (de la lista de abajo del total). */
  function quitarAsiento(clave: string, asiento: number) {
    const g = grupo(clave);
    if (g) fijarAsientosDe(clave, g.asientos.filter((id) => id !== asiento));
  }

  function fijarPasajero(clave: string, asiento: number, pasajero: Cliente | null) {
    const g = grupo(clave);
    if (g) g.pasajeros[asiento] = pasajero;
  }

  function alternarAsiento(id: number) {
    if (noVendible.value) return;
    if (reasignacion.value && !seleccion.value.includes(id) && seleccion.value.length >= reasignacion.value.boletos.length) {
      notify.warning(`Solo puede elegir ${reasignacion.value.boletos.length} asiento(s): uno por boleto. Quite uno para cambiarlo.`);
      return;
    }
    seleccion.value = alternar(seleccion.value, id);
  }

  /** Última cotización pedida por grupo: la respuesta de una anterior se descarta. */
  const cotizando = new Map<string, number>();
  async function cotizarGrupo(clave: string, cortesia = false) {
    const g = grupo(clave);
    if (!g) return;
    const n = (cotizando.get(clave) ?? 0) + 1;
    cotizando.set(clave, n);
    const vigente = () => (cotizando.get(clave) === n ? grupo(clave) : null);
    g.errorCotizacion = "";
    try {
      const c = await api.cotizar({
        salida: g.salidaId,
        trayecto: g.trayectoId,
        asientos: [...g.asientos],
        cobrarTrayectoCompleto: g.cobrarTrayectoCompleto,
        cortesia,
        venta: reasignacion.value?.ventaId,
      });
      const actual = vigente();
      if (actual) actual.cotizacion = c;
    } catch (e) {
      const actual = vigente();
      if (!actual) return;
      actual.cotizacion = null;
      actual.errorCotizacion = api.errorVenta(e)?.error ?? "No se pudo calcular la tarifa.";
    }
  }

  /** Vuelve a cotizar todo lo elegido (como cortesía, al emitirla). */
  async function recotizar(cortesia = false) {
    await Promise.all(grupos.value.map((g) => cotizarGrupo(g.clave, cortesia)));
  }

  /**
   * Registra una venta por grupo, uno tras otro: cada una con su factura
   * (la de la empresa de su salida) y su ticket. Devuelve los comprobantes de
   * las que se registraron. Si una falla, se detiene ahí: las anteriores
   * quedan vendidas y esa y las siguientes siguen elegidas; si fue el
   * certificador, el motivo queda en `falloFacturacion` (reintentar sigue
   * desde esa). Sin factura electrónica solo va la primera (la que falló).
   */
  async function vender(opciones: OpcionesCobro): Promise<Comprobante[]> {
    if (!puedeVender.value || !cliente.value) return [];
    vendiendo.value = true;
    falloFacturacion.value = null;
    const hechos: Comprobante[] = [];
    let sinFactura = opciones.sinFacturaElectronica ?? false;
    enviando = true;
    try {
      // `grupos` se reemplaza al vender cada una: se recorre la lista de antes.
      for (const g of grupos.value) {
        try {
          hechos.push(
            await api.vender({
              token: g.token,
              salida: g.salidaId,
              trayecto: g.trayectoId,
              asientos: g.asientos.map((asiento) => ({ asiento, cliente: g.pasajeros[asiento]?.id ?? null })),
              cliente: cliente.value.id,
              // Lugar de emisión: la estación del usuario, no el filtro "Sube en" (que es dónde sube el pasajero).
              estacion: contexto.value?.canal === "estacion" ? (contexto.value.estacion?.id ?? null) : null,
              observacion: observacion.value.trim() || null,
              cobrarTrayectoCompleto: g.cobrarTrayectoCompleto,
              tipoPago: opciones.tipoPago,
              moneda: opciones.moneda,
              enviarCorreo: opciones.enviarCorreo,
              cortesia: opciones.cortesia,
              sinFacturaElectronica: sinFactura,
            }),
          );
          grupos.value = grupos.value.filter((x) => x.clave !== g.clave);
          sinFactura = false;
        } catch (e) {
          await rechazada(e, g, hechos.length > 0 || grupos.value.length > 1);
          break;
        }
      }
    } finally {
      enviando = false;
      vendiendo.value = false;
    }
    if (hechos.length) {
      comprobantes.value = [...comprobantes.value, ...hechos];
      comprobanteReasignado.value = false;
      if (!grupos.value.length) terminarVenta();
      if (contexto.value?.canal === "agencia") void refrescarContexto();
      void refrescarOcupacion();
      void cargarSalidas();
    }
    return hechos;
  }

  /** La venta de un grupo no se registró: el motivo, con su viaje delante si hay más de una. */
  async function rechazada(e: unknown, g: GrupoVenta, conViaje: boolean) {
    const err = api.errorVenta(e);
    const motivo = (texto: string) => (conViaje ? `${g.ruta} (${fechaCorta(g.fecha)}): ${texto}` : texto);
    if (err?.codigo === "facturacion") {
      falloFacturacion.value = { ...err, error: motivo(err.error) };
      return;
    }
    // Sin el permiso de cortesía o pasado su límite se ofrece pedir una autorización (ADR-040).
    if (useAutorizacionesStore().ofrecer(e)) return;
    notify.error(motivo(err?.error ?? "No se pudo registrar la venta."));
    if (err?.codigo === "asientos_no_disponibles") {
      if (g.salidaId === salidaId.value) await refrescarOcupacion();
      else await depurarOtrasSalidas(g.salidaId);
    }
    if (err?.codigo === "saldo_insuficiente") void refrescarContexto();
  }

  /** Deja lista la pantalla para la siguiente venta (mismo salida y cliente). */
  function terminarVenta() {
    grupos.value = [];
    cotizando.clear();
    observacion.value = "";
    falloFacturacion.value = null;
  }

  /**
   * «Nueva venta»: cierra los comprobantes y limpia cliente y salida; conserva
   * fecha, estación y filtros. Si una venta quedó sin registrar, sus asientos
   * siguen elegidos.
   */
  function nuevaVenta() {
    comprobantes.value = [];
    if (grupos.value.length) return;
    cliente.value = null;
    observacion.value = "";
    cerrarSalida();
  }

  /** No se reintenta la que falló: lo que no se vendió sigue elegido, con claves nuevas. */
  function cancelarVenta() {
    falloFacturacion.value = null;
    for (const g of grupos.value) g.token = nuevoToken();
  }

  /**
   * Pone la pantalla en modo reasignación para esos boletos: carga los datos
   * de la venta (cliente) y deja elegir salida y asientos. Devuelve false si
   * no se puede (y avisa por qué).
   */
  async function iniciarReasignacion(ids: number[]): Promise<boolean> {
    if (!contexto.value) await iniciar();
    let boletos: BoletoOperable[];
    try {
      boletos = await api.fetchBoletos(ids);
    } catch (e) {
      notify.error(api.errorVenta(e)?.error ?? "No se pudieron cargar los boletos.");
      return false;
    }
    const bloqueo = motivoNoOperable(boletos);
    if (bloqueo) {
      notify.error(bloqueo);
      return false;
    }
    if (new Set(boletos.map((b) => b.venta.id)).size > 1) {
      notify.error("Los boletos de una reasignación deben ser de una misma venta.");
      return false;
    }
    cerrarSalida();
    grupos.value = [];
    const filtros = reasignacion.value?.filtros ?? { estacionId: estacionId.value, destinoId: destinoId.value };
    reasignacion.value = { boletos, ventaId: boletos[0]!.venta.id, filtros };
    cliente.value = boletos[0]!.venta.cliente;
    const salida = new Date(boletos[0]!.salida.fecha);
    fecha.value = salida.getTime() > Date.now() ? salida : new Date();
    empresaIds.value = [];
    // Las salidas que hacen el mismo viaje que el boleto.
    estacionId.value = boletos[0]!.trayecto.origenId;
    destinoId.value = boletos[0]!.trayecto.destinoId;
    porRango.value = false;
    await cargarSalidas();
    return true;
  }

  function cancelarReasignacion() {
    terminarReasignacion();
    cliente.value = null;
    cerrarSalida();
    terminarVenta();
    void cargarSalidas();
  }

  /** Sale del modo reasignación y repone los filtros "Sube en"/"Baja en" que había. */
  function terminarReasignacion() {
    const filtros = reasignacion.value?.filtros;
    reasignacion.value = null;
    if (!filtros) return;
    estacionId.value = filtros.estacionId;
    destinoId.value = filtros.destinoId;
  }

  /** Registra la reasignación. Devuelve el comprobante de los boletos nuevos (para imprimir). */
  async function reasignar(): Promise<Comprobante | null> {
    if (!puedeReasignar.value || !reasignacion.value || !salidaId.value) return null;
    vendiendo.value = true;
    try {
      enviando = true;
      const c = await api.reasignarBoletos({
        boletos: reasignacion.value.boletos.map((b) => b.id),
        salida: salidaId.value,
        trayecto: trayectoId.value,
        asientos: seleccion.value,
        cobrarTrayectoCompleto: cobrarTrayectoCompleto.value,
      });
      enviando = false;
      comprobantes.value = [c];
      comprobanteReasignado.value = true;
      terminarReasignacion();
      cliente.value = null;
      terminarVenta();
      void refrescarOcupacion();
      void cargarSalidas();
      return c;
    } catch (e) {
      enviando = false;
      const err = api.errorVenta(e);
      // Sin permiso, pasado el límite o fuera de hora: se ofrece pedir una autorización (ADR-040).
      if (useAutorizacionesStore().ofrecer(e)) return null;
      notify.error(err?.error ?? "No se pudo reasignar.");
      if (err?.codigo === "asientos_no_disponibles") await refrescarOcupacion();
      return null;
    } finally {
      vendiendo.value = false;
    }
  }

  return {
    reasignacion,
    comprobanteReasignado,
    parejas,
    puedeReasignar,
    iniciarReasignacion,
    cancelarReasignacion,
    nuevaVenta,
    reasignar,
    contexto,
    errorCarga,
    fecha,
    estacionId,
    salidas,
    empresaIds,
    empresas,
    destinoId,
    porRango,
    desde,
    hasta,
    salidasVisibles,
    visibles,
    cargandoSalidas,
    salidaId,
    detalle,
    cargandoDetalle,
    sube,
    baja,
    ocupados,
    asientoPorAbrir,
    seleccion,
    cliente,
    pasajeros,
    observacion,
    cobrarTrayectoCompleto,
    cotizacion,
    errorCotizacion,
    vendiendo,
    falloFacturacion,
    comprobantes,
    grupos,
    grupoActual,
    claveActual,
    totalAsientos,
    variasSalidas,
    quitarAsiento,
    fijarPasajero,
    trayectoId,
    esTramoIntermedio,
    puedeCobrarCompleto,
    clasesVendibles,
    noVendible,
    estadoAsiento,
    asientosCroquis,
    libres,
    puedeVender,
    total,
    iniciar,
    cargarSalidas,
    cambiarFiltroEstacion,
    elegirSalida,
    cerrarSalida,
    alternarAsiento,
    recotizar,
    vender,
    terminarVenta,
    cancelarVenta,
  };
});
