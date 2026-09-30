import { Controller } from "@hotwired/stimulus";
/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */

export default class extends Controller {
  static targets = [
    "asientos_salida",
    "asientos_regreso",
    "nivel1",
    "nivel2",
    "btn_nivel1",
    "btn_nivel2",
    "nivel1_regreso",
    "nivel2_regreso",
    "btn_nivel1_regreso",
    "btn_nivel2_regreso",
  ];
  static values = {
    asientosNivel1: Number,
    asientosNivel2: Number,
    regreso: String,
  };

  asientos_salida = [];
  asientos_regreso = [];
  connect() {
    this.dispatch("paso_completado", { detail: { paso: 2 } });
    window.scrollTo({ top: 0, behavior: "smooth" });

    // if (!this.hasNivel2Target) {
    //   this.nivel1Target.style.height =
    //     parseInt((this.nivel1Target.dataset.asientos / 4) * 60 + 350) + "px";
    // } else {
    //   this.nivel1Target.style.height =
    //     parseInt((this.nivel1Target.dataset.asientos / 3) * 60 + 350) + "px";
    //   this.nivel2Target.style.height =
    //     parseInt((this.nivel2Target.dataset.asientos / 4) * 60 + 350) + "px";
    // }

    if (this.hasNivel1_regresoTarget) {
      if (!this.hasNivel2_regresoTarget) {
        this.nivel1_regresoTarget.style.height =
          parseInt(
            (this.nivel1_regresoTarget.dataset.asientos / 4) * 60 + 250
          ) + "px";
      } else {
        this.nivel1_regresoTarget.style.height =
          parseInt(
            (this.nivel1_regresoTarget.dataset.asientos / 3) * 60 + 250
          ) + "px";
        this.nivel2_regresoTarget.style.height =
          parseInt(
            (this.nivel2_regresoTarget.dataset.asientos / 4) * 60 + 250
          ) + "px";
      }
    }

    this.asientos_salida = JSON.parse(this.asientos_salidaTarget.value);

    if (this.hasAsientos_regresoTarget) {
      this.asientos_regreso = JSON.parse(this.asientos_regresoTarget.value);
    }
  }

  cambiar_nivel(event) {
    let nivel1, nivel2, btn_nivel1, btn_nivel2;
    if (!event.currentTarget.dataset.regreso) {
      nivel1 = this.nivel1Target;
      nivel2 = this.nivel2Target;
      btn_nivel1 = this.btn_nivel1Target;
      btn_nivel2 = this.btn_nivel2Target;
    } else {
      nivel1 = this.nivel1_regresoTarget;
      nivel2 = this.nivel2_regresoTarget;
      btn_nivel1 = this.btn_nivel1_regresoTarget;
      btn_nivel2 = this.btn_nivel2_regresoTarget;
    }
    if (event.currentTarget.dataset.nivel == 1) {
      nivel2.classList.remove("hidden");
      nivel1.classList.add("hidden");
      btn_nivel2.classList.remove("active");
      btn_nivel1.classList.add("active");
    } else {
      nivel1.classList.remove("hidden");
      nivel2.classList.add("hidden");

      btn_nivel2.classList.add("active");
      btn_nivel1.classList.remove("active");
    }
  }

  elegirAsiento(event) {
    if (event.currentTarget.dataset.ocupado) {
      return;
    }
    const id = parseInt(event.currentTarget.dataset.id);
    const numero = parseInt(event.currentTarget.dataset.numero);
    let asientos = event.currentTarget.dataset.regreso
      ? this.asientos_regreso
      : this.asientos_salida;

    const index = asientos.indexOf(asientos.find((i) => i.id == id));
    if (index != -1) {
      asientos.splice(index, 1);
      event.currentTarget.classList.remove("elegido");
    } else {
      event.currentTarget.classList.add("elegido");
      asientos.push({ id: id, numero: numero });
    }
    if (event.currentTarget.dataset.regreso) {
      this.asientos_regreso = asientos;
      this.asientos_regresoTarget.value = JSON.stringify(this.asientos_regreso);
    } else {
      this.asientos_salida = asientos;
      this.asientos_salidaTarget.value = JSON.stringify(this.asientos_salida);
    }
  }
}
