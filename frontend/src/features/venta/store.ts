/**
 * Estado de la pantalla de venta (taquilla y agencias, ADR-021): filtros,
 * salida elegido, tramo, ocupación en vivo (Mercure), selección de
 * asientos, cliente y cotización. La lógica pura está en `core/venta/modelo`.
 */
import { defineStore } from "pinia";
import { computed, ref, shallowRef } from "vue";
import * as api from "@/core/venta/api";
import { alternar, bajadaPorDefecto, clasesDelTrayecto, depurarSeleccion, diaISO, estadoEnMapa, nuevoToken, paradasDeBajada, paradasDeSubida, soloVendibles, subidaPorDefecto, trayectoEntre } from "@/core/venta/modelo";
import { suscribir } from "@/core/realtime";
import { notify } from "@/core/notify";
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

export const useVentaStore = defineStore("venta", () => {
  const contexto = shallowRef<ContextoVenta | null>(null);
  const errorCarga = ref("");

  const fecha = ref(new Date());
  const estacionId = ref<number | null>(null);
  const salidas = ref<SalidaResumen[]>([]);
  const cargandoSalidas = ref(false);
  /** Filtro por empresa (en el cliente, sobre las salidas ya cargadas); null = todas. */
  const empresaId = ref<number | null>(null);
  const empresas = computed(() => {
    const porId = new Map<number, string>();
    for (const r of salidas.value) if (r.empresa) porId.set(r.empresa.id, r.empresa.nombre);
    return [...porId].map(([id, nombre]) => ({ id, nombre })).sort((a, b) => a.nombre.localeCompare(b.nombre));
  });
  /** Reasignando: los boletos que se pasan a otro asiento o salida (todos de una venta); null = venta normal. */
  const reasignacion = shallowRef<{ boletos: BoletoOperable[]; ventaId: number } | null>(null);
  /** El comprobante que se muestra es de una reasignación (no de una venta). */
  const comprobanteReasignado = ref(false);
  const salidasVisibles = computed(() => {
    const filtradas = empresaId.value == null ? salidas.value : salidas.value.filter((r) => r.empresa?.id === empresaId.value);
    return reasignacion.value ? salidasReasignables(filtradas, reasignacion.value.boletos, new Date()) : filtradas;
  });

  const salidaId = ref<number | null>(null);
  const detalle = shallowRef<SalidaDetalle | null>(null);
  const cargandoDetalle = ref(false);
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

  const trayectoId = computed(() => (detalle.value ? trayectoEntre(detalle.value, sube.value, baja.value) : null));
  const esSubtrayecto = computed(() => !!detalle.value && trayectoId.value !== detalle.value.trayecto.id);
  /** Cobrar el trayecto completo solo si se viaja un subtrayecto y el completo tiene tarifa. */
  const puedeCobrarCompleto = computed(() => esSubtrayecto.value && !!detalle.value?.trayectos.some((t) => t.completo));
  const subidas = computed(() => (detalle.value ? paradasDeSubida(detalle.value) : []));
  const bajadas = computed(() => (detalle.value ? paradasDeBajada(detalle.value, sube.value) : []));
  /** Clases de asiento con tarifa en el tramo elegido: las demás se ven bloqueadas. */
  const clasesVendibles = computed(() => (detalle.value ? clasesDelTrayecto(detalle.value, trayectoId.value) : []));
  /** Motivo por el que el salida ya no se vende (p. ej. de un día pasado): el croquis queda de solo lectura. */
  const noVendible = computed(() => detalle.value?.noVendible ?? null);
  const estadoAsiento = computed(() => estadoEnMapa(ocupados.value, seleccion.value, noVendible.value ? [] : clasesVendibles.value));
  const asientosCroquis = computed(() => detalle.value?.croquis.filter((e): e is AsientoCroquis => e.tipo === "asiento") ?? []);
  const libres = computed(() => asientosCroquis.value.length - ocupados.value.filter((o) => o.estado !== "propio").length);
  const preciosCotizados = computed(() => new Map<number, Importe>((cotizacion.value?.asientos ?? []).map((a) => [a.asiento, a.precio])));
  /** Reasignando: cada boleto con el asiento que se le eligió y si cuesta lo mismo. */
  const parejas = computed(() => (reasignacion.value ? emparejar(reasignacion.value.boletos, seleccion.value, preciosCotizados.value) : []));
  const puedeReasignar = computed(() => !!reasignacion.value && !!detalle.value && !noVendible.value && trayectoId.value != null && listaParaReasignar(parejas.value) && !vendiendo.value);
  const puedeVender = computed(() => !!cliente.value && !!detalle.value && !noVendible.value && trayectoId.value != null && seleccion.value.length > 0 && !vendiendo.value);

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
      salidas.value = await api.fetchSalidas(diaISO(fecha.value), estacionId.value);
      if (empresaId.value != null && !salidas.value.some((r) => r.empresa?.id === empresaId.value)) empresaId.value = null;
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

  async function elegirSalida(id: number) {
    if (id === salidaId.value && detalle.value) return;
    cerrarSalida();
    salidaId.value = id;
    cargandoDetalle.value = true;
    try {
      const d = await api.fetchSalida(id);
      if (salidaId.value !== id) return;
      detalle.value = d;
      sube.value = subidaPorDefecto(d, estacionId.value);
      baja.value = bajadaPorDefecto(d, sube.value);
      // Reasignando: el mismo tramo que tenía el boleto, si la salida lo ofrece.
      const tramo = reasignacion.value ? tramoDelBoleto(d, reasignacion.value.boletos[0]!) : null;
      if (tramo) {
        sube.value = tramo.sube;
        baja.value = tramo.baja;
      }
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
    seleccion.value = [];
    pasajeros.value = {};
    cotizacion.value = null;
    cobrarTrayectoCompleto.value = false;
  }

  async function cargarOcupacion(silenciosa = false) {
    if (!salidaId.value || trayectoId.value == null) return;
    ocupados.value = await api.fetchOcupacion(salidaId.value, trayectoId.value, silenciosa);
  }

  /** Refresco en vivo: si otro vendió un asiento elegido, se avisa y se quita. */
  async function refrescarOcupacion() {
    try {
      await cargarOcupacion(true);
    } catch {
      return;
    }
    const depurada = depurarSeleccion(seleccion.value, ocupados.value);
    if (depurada.length !== seleccion.value.length) {
      notify.warning("Otro vendedor tomó un asiento que tenía elegido: se quitó de la selección.");
      seleccion.value = depurada;
      void recotizar();
    }
  }

  async function cambiarSubida(id: number | null) {
    sube.value = id;
    if (detalle.value && !bajadas.value.some((p) => p.id === baja.value)) {
      baja.value = bajadaPorDefecto(detalle.value, id);
    }
    await cambioDeTramo();
  }

  async function cambiarBajada(id: number | null) {
    baja.value = id;
    await cambioDeTramo();
  }

  async function cambioDeTramo() {
    if (!puedeCobrarCompleto.value) cobrarTrayectoCompleto.value = false;
    await cargarOcupacion();
    seleccion.value = soloVendibles(depurarSeleccion(seleccion.value, ocupados.value), asientosCroquis.value, clasesVendibles.value);
    await recotizar();
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
      const c = await api.vender({
        token: token.value,
        salida: salidaId.value,
        trayecto: trayectoId.value,
        asientos: seleccion.value.map((asiento) => ({
          asiento,
          cliente: pasajeros.value[asiento]?.id ?? null,
        })),
        cliente: cliente.value.id,
        estacion: contexto.value?.canal === "estacion" ? estacionId.value : null,
        observacion: observacion.value.trim() || null,
        cobrarTrayectoCompleto: cobrarTrayectoCompleto.value,
        tipoPago: opciones.tipoPago,
        moneda: opciones.moneda,
        enviarCorreo: opciones.enviarCorreo,
        cortesia: opciones.cortesia,
        sinFacturaElectronica: opciones.sinFacturaElectronica ?? false,
      });
      comprobante.value = c;
      comprobanteReasignado.value = false;
      terminarVenta();
      if (contexto.value?.canal === "agencia") void refrescarContexto();
      void refrescarOcupacion();
      void cargarSalidas();
      return c;
    } catch (e) {
      const err = api.errorVenta(e);
      if (err?.codigo === "facturacion") {
        falloFacturacion.value = err;
      } else {
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
    reasignacion.value = { boletos, ventaId: boletos[0]!.venta.id };
    cliente.value = boletos[0]!.venta.cliente;
    const salida = new Date(boletos[0]!.salida.fecha);
    fecha.value = salida.getTime() > Date.now() ? salida : new Date();
    empresaId.value = null;
    await cargarSalidas();
    return true;
  }

  function cancelarReasignacion() {
    reasignacion.value = null;
    cliente.value = null;
    cerrarSalida();
    token.value = nuevoToken();
  }

  /** Registra la reasignación. Devuelve el comprobante de los boletos nuevos (para imprimir). */
  async function reasignar(): Promise<Comprobante | null> {
    if (!puedeReasignar.value || !reasignacion.value || !salidaId.value) return null;
    vendiendo.value = true;
    try {
      const c = await api.reasignarBoletos({
        boletos: reasignacion.value.boletos.map((b) => b.id),
        salida: salidaId.value,
        trayecto: trayectoId.value,
        asientos: seleccion.value,
        cobrarTrayectoCompleto: cobrarTrayectoCompleto.value,
      });
      comprobante.value = c;
      comprobanteReasignado.value = true;
      reasignacion.value = null;
      cliente.value = null;
      terminarVenta();
      void refrescarOcupacion();
      void cargarSalidas();
      return c;
    } catch (e) {
      const err = api.errorVenta(e);
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
    reasignar,
    contexto,
    errorCarga,
    fecha,
    estacionId,
    salidas,
    empresaId,
    empresas,
    salidasVisibles,
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
    esSubtrayecto,
    puedeCobrarCompleto,
    clasesVendibles,
    noVendible,
    subidas,
    bajadas,
    estadoAsiento,
    asientosCroquis,
    libres,
    puedeVender,
    iniciar,
    cargarSalidas,
    elegirSalida,
    cerrarSalida,
    cambiarSubida,
    cambiarBajada,
    alternarAsiento,
    recotizar,
    vender,
    terminarVenta,
    cancelarVenta,
  };
});
