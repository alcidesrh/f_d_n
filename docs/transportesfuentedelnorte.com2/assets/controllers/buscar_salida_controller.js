import { Controller } from "@hotwired/stimulus";
import flatpickr from "flatpickr";
import "flatpickr/dist/l10n/es.js";
/*
 * The following line makes this controller 'lazy': it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = [
    "buscar_salidas_submit",
    "lista",
    "salida_fecha_ida",
    "salida_fecha_regreso",
    "hora",
    "bus_clase",
    "minutos",
    "salida_id",
    "is_ida_vuelta",
    "empresa",
  ];
  static values = {
    idioma: String,
    idaVuelta: String,
  };

  salida_calendario = null;
  salida_id = null;

  initialize() {
    if (this.hasListaTarget) {
      const el = this.listaTarget.querySelector(".salida-selected");
      if (el) {
        this.salida_id = el.dataset.id;
      }
    }
  }
  abrirFechaSalida() {
    this.salida_calendario.open();
  }

  idiomaValueChanged() {
    const target = !this.idaVueltaValue
      ? this.salida_fecha_idaTarget
      : this.salida_fecha_regresoTarget;

    this.salida_calendario = flatpickr(target, {
      locale: this.idiomaValue,
      dateFormat: this.idiomaValue == "es" ? "d/m/Y" : "Y-m-d",
      disableMobile: "true",
      minDate: "today",
      appendTo: document.querySelector(
        ".salida_form_" + (this.idaVueltaValue ? "regreso" : "ida")
      ),
      onOpen: () => {
        let dt = this.salida_calendario.selectedDates[0];
        let test = new Date(dt.getTime());
        test.setDate(test.getDate() + 1);
        if (test.getDate() === 1) {
          this.salida_calendario.changeMonth(1);
        }
      },
    });
    if (this.hasIs_ida_vueltaTarget && target.value) {
      this.dispatch("min-fecha", {
        detail: { fecha: target.value },
      });
    }
  }

  buscarSalida() {
    this.buscar_salidas_submitTarget.click();
  }

  elegir(event) {
    const el = this.listaTarget.querySelector(".salida-selected");
    if (el) {
      el.classList.remove("salida-selected");
    }

    if (this.salida_id == event.currentTarget.dataset.id) {
      this.salida_id = null;
      this.salida_idTarget.value = null;
      this.buscar_salidas_submitTarget.click();
      return;
    }
    this.salida_id = event.currentTarget.dataset.id;

    this.listaTarget
      .querySelector("#_" + event.currentTarget.dataset.id)
      .classList.add("salida-selected");

    this.empresaTarget.value = event.currentTarget.dataset.empresa;

    this.horaTarget.value = event.currentTarget.querySelector(
      `#_${this.salida_id}_horario`
    ).dataset.value;

    // this.bus_claseTarget.value = event.currentTarget.querySelector(
    //   `#_${this.salida_id}_bus_clase`
    // ).dataset.value;

    // this.minutosTarget.value = event.currentTarget.querySelector(
    //   `#_${this.salida_id}_minutos`
    // ).dataset.value;
    this.salida_idTarget.value = this.salida_id;

    this.buscar_salidas_submitTarget.click();
  }

  minFecha(event) {
    this.salida_calendario = flatpickr(this.salida_fecha_regresoTarget, {
      locale: this.idiomaValue,
      dateFormat: this.idiomaValue == "es" ? "d/m/Y" : "Y-m-d",
      minDate: event.detail.fecha,
      disableMobile: "true",
      appendTo: document.querySelector(".salida_form_regreso"),
    });
  }
}
