// import * as Turbo from "@hotwired/turbo";
import { spinner } from "../util";

import { Controller } from "@hotwired/stimulus";
/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = ["pasos", "alert"];
  static values = {
    paso: Number,
  };
  paso = null;
  connect() {
    if (this.pasoValue) {
      this.blur();
      this.sliderStop(true);
    } else {
      this.blur(false);
      this.sliderStop(false);
    }

    this.paso = this.pasoValue;

    document.addEventListener("turbo:click", (event) => {
      if (!event.target.dataset.nospinner) {
        spinner(event.target, true);
      }
    });

    document.addEventListener("turbo:submit-start", (event) => {
      if (event.target.dataset.pagar) {
        const mensaje = document.getElementById("msg-pagando");
        if (mensaje) {
          mensaje.classList.remove("hidden");
        }
        const loading = document.getElementById("turbo-loading");
        if (loading) {
          document.getElementById("turbo-loading").classList.add("!flex");
        }
      } else if (!event.target.dataset.nospinner) {
        spinner(event.detail.formSubmission);
      }
    });

    // document.addEventListener(
    //   "turbo:before-fetch-request",
    //   async function (event) {
    //     document.removeEventListener("typed-stop", _onStopTyped);
    //   }
    // );

    document.addEventListener("turbo:before-fetch-response", (event) => {
      const fetchResponse = event.detail.fetchResponse;

      // document.addEventListener("typed-stop", _onStopTyped);

      if (fetchResponse.response.headers.get("session-terminada")) {
        event.preventDefault();
        window.location = fetchResponse.response.headers.get("Turbo-Location");
        return;
      } else if (fetchResponse.response.headers.get("procesando-pago")) {
        event.preventDefault();
      } else if (fetchResponse.response.headers.get("error-pago")) {
        event.preventDefault();

        document.querySelectorAll(".spinner-wrap").forEach((element) => {
          element.remove();
        });
        document.querySelectorAll(".spinner-temp").forEach((element) => {
          element.classList.remove("spinner-temp");
          element.classList.remove("!hidden");
        });
        const loading = document.getElementById("turbo-loading");
        if (loading) {
          document.getElementById("turbo-loading").classList.remove("!flex");
        }

        const msg = document.querySelector("#msg-pagando");
        if (msg) {
          msg.classList.add("hidden");
        }

        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    });

    // function _onStopTyped({ detail: { typed } }) {
    //   typed.stop();
    // }
    document.addEventListener("turbo:frame-load", (event) => {
      const loading = document.getElementById("turbo-loading");

      if (loading) {
        document.getElementById("turbo-loading").classList.remove("!flex");
      }
    });
  }

  disconnect() {
    document.removeEventListener("turbo:click", () => { });
    document.removeEventListener("turbo:submit-start", () => { });
    document.removeEventListener("turbo:before-fetch-response", () => { });
    document.removeEventListener("turbo:frame-load", () => { });
    document.removeEventListener("turbo:before-fetch-request", async () => { });
  }

  sliderStop(stop) {
    this.dispatch("slider", { detail: { stop: stop } });
  }

  setPaso(event) {
    event.preventDefault();
    if (this.hasAlertTarget && event.detail.paso != 0 && this.alertTarget) {
      this.alertTarget.classList.add("hidden");
    }
    this.cleanError();
    if (event.detail.paso > 0) {
      document.querySelector("nav").classList.add("hidden", "lg:flex");
      document.querySelector("#div-nav-height").classList.add("hidden", "lg:flex");
      document.querySelector("#reserva-wrap").classList.add("reserva");
    } else {
      document.querySelector("nav").classList.remove("hidden", "lg:flex");
      document.querySelector("#div-nav-height").classList.remove("hidden", "lg:flex");
      document.querySelector("#reserva-wrap").classList.remove("reserva");
    }

    if (this.paso == event.detail.paso) {
      return;
    }
    const paso = event.detail.paso;
    this.paso = paso;
    this.blur(paso);
    this.sliderStop(paso);

    if (
      this.pasosTarget.querySelector(".active") &&
      typeof this.pasosTarget.querySelector(".active") != "undefined"
    ) {
      this.pasosTarget.querySelector(".active").classList.remove("active");
    }
    if (paso >= 0 && this.pasosTarget.children[paso - 1]) {
      this.pasosTarget.children[paso - 1].classList.add("complete");
      // if (paso == 4) {
      //   this.blur(false);
      //   this.sliderStop(false);
      // }
    }
    if (
      typeof this.pasosTarget.children[paso] &&
      typeof this.pasosTarget.children[paso] != "undefined"
    ) {
      this.pasosTarget.children[paso].classList.remove("complete");
      this.pasosTarget.children[paso].classList.add("active");
    }
  }
  cleanError() {
    const error = document.querySelector('#error_generico')
    if (error) {
      error.innerText = ''
    }
  }
  blur(add = true) {
    if (add) {
      document.querySelector("main").classList.add("reservando");
    } else {
      document.querySelector("main").classList.remove("reservando");
    }
  }
}
