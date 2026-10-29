/**
 * Estado de la pantalla de venta (taquilla y agencias, ADR-021): filtros,
 * salida elegido, tramo, ocupación en vivo (Mercure), selección de
 * asientos, cliente y cotización. La lógica pura está en `core/venta/modelo`.
 */
import { defineStore } from "pinia";
import { computed, onScopeDispose, ref, shallowRef, watch } from "vue";
import { cotizarEncomienda } from "@/core/encomienda/api";
import { errorArticulos, limpiarArticulos } from "@/core/encomienda/reglas";
import type { ArticuloPedido, CotizacionEncomienda } from "@/core/encomienda/types";
import * as api from "@/core/venta/api";
import { alternar, bajadaPorDefecto, clasesDelTrayecto, depurarSeleccion, diaISO, estadoEnMapa, nuevoToken, subidaPorDefecto, trayectoEntre } from "@/core/venta/modelo";
import { suscribir } from "@/core/realtime";
import { notify } from "@/core/notify";
import { useAutorizacionesStore } from "@/core/autorizacion/store";
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

const dos = (n: number) => String(n).padStart(2, "0");
const inicioDeDia = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0);
const finDeDia = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59);
/** "AAAA-MM-DDTHH:MM" en hora local, como lo espera `/venta/salidas`. */
const localISO = (d: Date) => `${diaISO(d)}T${dos(d.getHours())}:${dos(d.getMinutes())}`;

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

  const seleccion = ref<number[]>([]);
  const cliente = ref<Cliente | null>(null);
  /** Pasajero por asiento (si no, viaja el cliente de la venta). */
  const pasajeros = ref<Record<number, Cliente | null>>({});
  const observacion = ref("");
  const cobrarTrayectoCompleto = ref(false);
  const cotizacion = shallowRef<Cotizacion | null>(null);
  const errorCotizacion = ref("");

  const vendiendo = ref(false);
  /** Clave de idempotencia de la venta en curso (se renueva al terminarla). */
  const token = ref(nuevoToken());
  const falloFacturacion = ref<ErrorVenta | null>(null);
  const comprobante = shallowRef<Comprobante | null>(null);

  let desuscribir: (() => void) | null = null;
  // Al descartar la store (cambio de usuario) deja de oír la ocupación.
  onScopeDispose(() => desuscribir?.());

  const trayectoId = computed(() => (detalle.value ? trayectoEntre(detalle.value, sube.value, baja.value) : null));
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
  // —— Encomienda del pasajero (ADR-035): de donde sube a donde baja, en la misma factura ——
  const encomiendaArticulos = ref<ArticuloPedido[]>([]);
  const encomiendaObservacion = ref("");
  const cotizacionEncomienda = shallowRef<CotizacionEncomienda | null>(null);
  const errorEncomienda = ref("");
  /** Sin encomienda, o con sus artículos completos y cotizados. */
  const encomiendaLista = computed(() => !encomiendaArticulos.value.length || (!errorArticulos(encomiendaArticulos.value) && !!cotizacionEncomienda.value && !errorEncomienda.value));
  let encomiendaEnCurso = 0;
  let esperaEncomienda: ReturnType<typeof setTimeout> | undefined;
  watch(
    [encomiendaArticulos, sube, baja],
    () => {
      clearTimeout(esperaEncomienda);
      errorEncomienda.value = "";
      if (!encomiendaArticulos.value.length || errorArticulos(encomiendaArticulos.value) || !sube.value || !baja.value) {
        cotizacionEncomienda.value = null;
        return;
      }
      esperaEncomienda = setTimeout(async () => {
        const n = ++encomiendaEnCurso;
        try {
          const c = await cotizarEncomienda({ origen: sube.value, destino: baja.value!, articulos: limpiarArticulos(encomiendaArticulos.value) });
          if (n === encomiendaEnCurso) cotizacionEncomienda.value = c;
        } catch (e) {
          if (n !== encomiendaEnCurso) return;
          cotizacionEncomienda.value = null;
          errorEncomienda.value = api.errorVenta(e)?.error ?? "No se pudo cotizar la encomienda.";
        }
      }, 350);
    },
    { deep: true },
  );
  onScopeDispose(() => clearTimeout(esperaEncomienda));
  /** Lo que se cobra: los asientos más la encomienda. */
  const total = computed<Importe | null>(() => {
    const asientos = cotizacion.value?.total ?? null;
    const encomienda = cotizacionEncomienda.value?.total ?? null;
    if (!asientos || !encomiendaArticulos.value.length || !encomienda) return asientos;
    const centavos = asientos.centavos + encomienda.centavos;
    return { centavos, moneda: asientos.moneda, texto: `${asientos.moneda === "GTQ" ? "Q" : asientos.moneda} ${(centavos / 100).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` };
  });

  const puedeVender = computed(() => !!cliente.value && !!detalle.value && !noVendible.value && trayectoId.value != null && seleccion.value.length > 0 && !vendiendo.value && encomiendaLista.value);

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
    seleccion.value = [];
    pasajeros.value = {};
    cotizacion.value = null;
    cobrarTrayectoCompleto.value = false;
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
      void recotizar();
    }
  }

  function alternarAsiento(id: number) {
    if (noVendible.value) return;
    if (reasignacion.value && !seleccion.value.includes(id) && seleccion.value.length >= reasignacion.value.boletos.length) {
      notify.warning(`Solo puede elegir ${reasignacion.value.boletos.length} asiento(s): uno por boleto. Quite uno para cambiarlo.`);
      return;
    }
    seleccion.value = alternar(seleccion.value, id);
    if (!seleccion.value.includes(id)) delete pasajeros.value[id];
    void recotizar();
  }

  let cotizacionEnCurso = 0;
  async function recotizar(cortesia = false) {
    const n = ++cotizacionEnCurso;
    errorCotizacion.value = "";
    if (!salidaId.value || !seleccion.value.length) {
      cotizacion.value = null;
      return;
    }
    try {
      const c = await api.cotizar({
        salida: salidaId.value,
        trayecto: trayectoId.value,
        asientos: seleccion.value,
        cobrarTrayectoCompleto: cobrarTrayectoCompleto.value,
        cortesia,
        venta: reasignacion.value?.ventaId,
      });
      if (n === cotizacionEnCurso) cotizacion.value = c;
    } catch (e) {
      if (n !== cotizacionEnCurso) return;
      cotizacion.value = null;
      errorCotizacion.value = api.errorVenta(e)?.error ?? "No se pudo calcular la tarifa.";
    }
  }

  /**
   * Registra la venta. Devuelve el comprobante; si el certificador falló,
   * deja el motivo en `falloFacturacion` (la venta no quedó registrada).
   */
  async function vender(opciones: OpcionesCobro): Promise<Comprobante | null> {
    if (!puedeVender.value || !salidaId.value || !cliente.value) return null;
    vendiendo.value = true;
    falloFacturacion.value = null;
    try {
      enviando = true;
      const c = await api.vender({
        token: token.value,
        salida: salidaId.value,
        trayecto: trayectoId.value,
        asientos: seleccion.value.map((asiento) => ({
          asiento,
          cliente: pasajeros.value[asiento]?.id ?? null,
        })),
        cliente: cliente.value.id,
        // Lugar de emisión: la estación del usuario, no el filtro "Sube en" (que es dónde sube el pasajero).
        estacion: contexto.value?.canal === "estacion" ? (contexto.value.estacion?.id ?? null) : null,
        observacion: observacion.value.trim() || null,
        cobrarTrayectoCompleto: cobrarTrayectoCompleto.value,
        tipoPago: opciones.tipoPago,
        moneda: opciones.moneda,
        enviarCorreo: opciones.enviarCorreo,
        cortesia: opciones.cortesia,
        sinFacturaElectronica: opciones.sinFacturaElectronica ?? false,
        encomiendas: encomiendaArticulos.value.length && !opciones.cortesia ? [{ articulos: limpiarArticulos(encomiendaArticulos.value), observacion: encomiendaObservacion.value.trim() || null }] : [],
      });
      enviando = false;
      comprobante.value = c;
      comprobanteReasignado.value = false;
      terminarVenta();
      if (contexto.value?.canal === "agencia") void refrescarContexto();
      void refrescarOcupacion();
      void cargarSalidas();
      return c;
    } catch (e) {
      enviando = false;
      const err = api.errorVenta(e);
      if (err?.codigo === "facturacion") {
        falloFacturacion.value = err;
      } else if (!useAutorizacionesStore().ofrecer(e)) {
        // Sin el permiso de cortesía o pasado su límite se ofrece pedir una autorización (ADR-040).
        notify.error(err?.error ?? "No se pudo registrar la venta.");
        if (err?.codigo === "asientos_no_disponibles") await refrescarOcupacion();
        if (err?.codigo === "saldo_insuficiente") void refrescarContexto();
      }
      return null;
    } finally {
      vendiendo.value = false;
    }
  }

  /** Deja lista la pantalla para la siguiente venta (mismo salida y cliente). */
  function terminarVenta() {
    token.value = nuevoToken();
    seleccion.value = [];
    pasajeros.value = {};
    observacion.value = "";
    cotizacion.value = null;
    falloFacturacion.value = null;
    encomiendaArticulos.value = [];
    encomiendaObservacion.value = "";
    cotizacionEncomienda.value = null;
  }

  /** «Nueva venta»: cierra el comprobante y limpia cliente y salida; conserva fecha, estación y filtros. */
  function nuevaVenta() {
    comprobante.value = null;
    cliente.value = null;
    observacion.value = "";
    cerrarSalida();
    token.value = nuevoToken();
  }

  function cancelarVenta() {
    falloFacturacion.value = null;
    token.value = nuevoToken();
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
    token.value = nuevoToken();
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
      comprobante.value = c;
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
    seleccion,
    cliente,
    pasajeros,
    observacion,
    cobrarTrayectoCompleto,
    cotizacion,
    errorCotizacion,
    vendiendo,
    falloFacturacion,
    comprobante,
    trayectoId,
    esTramoIntermedio,
    puedeCobrarCompleto,
    clasesVendibles,
    noVendible,
    estadoAsiento,
    asientosCroquis,
    libres,
    puedeVender,
    encomiendaArticulos,
    encomiendaObservacion,
    cotizacionEncomienda,
    errorEncomienda,
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
