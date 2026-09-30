import { Controller } from "@hotwired/stimulus";
import IMask from "imask";
import axios from "axios";
/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = [
    "precio",
    "municipio",
    "nombre_factura",
    "nit",
    "nit_error",
    "nombre_factura_alert",
  ];
  static values = {
    precio: Number,
    precioDolar: Number,
  };

  nombre = "";
  appellido = "";

  connect() {
    this.dispatch("paso_completado", { detail: { paso: 3 } });
    window.scrollTo({ top: 0, behavior: "smooth" });

    this.mask();
  }

  disconnect() {
    document.querySelector("#msg-pagando").classList.add("hidden");
  }

  moneda(event) {
    this.precioTarget.innerHTML = event.currentTarget.dataset.precio;
  }

  mask() {
    IMask(document.querySelector("#pago_datos_numero"), {
      mask: "0000 0000 0000 000[0]",
    });

    IMask(document.querySelector("#pago_datos_codigo_seguridad"), {
      mask: "000[0]",
    });
  }

  onSubmit() {
    document.querySelector("#error_pago > *")?.remove();
  }

  nombreChange(e) {
    this.nombre = e.currentTarget.value;
    this.nombre_facturaTarget.value = `${this.nombre} ${this.apellido ?? ""}`;
    this.nombre_factura_alertTarget.innerHTML = `${this.nombre} ${this.nombre}`;
  }

  apellidoChange(e) {
    this.apellido = e.currentTarget.value;
    this.nombre_facturaTarget.value = `${this.nombre} ${this.apellido}`;
    this.nombre_factura_alertTarget.innerHTML = `${this.nombre} ${this.apellido}`;
  }

  validarNit($e) {
    $e.preventDefault();
    this.nit_errorTarget.classList.add("hidden");

    // const button = $e.currentTarget;
    // button.classList.add("hidden");

    if (!this.nitTarget.value || this.nitTarget.value === "CF") {
      this.element.requestSubmit();
      return;
    }
    axios
      .get(`/buscar-nit/${this.nitTarget.value}`)
      .then((response) => {
        this.nombre_facturaTarget.value = response.data.RazonSocial;
        this.nombre_factura_alertTarget.innerHTML = response.data.RazonSocial;
        this.element.requestSubmit();
      })
      .catch((error) => {
        this.nit_errorTarget.classList.remove("hidden");
        this.nit_errorTarget.innerHTML = error.response.data.error;
        this.nombre_facturaTarget.value = "";
        this.nombre_factura_alertTarget.innerHTML = "";
        this.nitTarget.value = "";
      });
    // .finally((e) => {
    //   button.classList.remove("hidden");
    // });
  }
}
