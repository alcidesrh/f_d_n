import { Controller } from "@hotwired/stimulus";

import { spinner } from "../util";

/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = ["test"];
  static values = {
    v: String,
  };

  static targets = ["pasos", "alert"];
  static values = {
    paso: Number,
  };
  paso = null;
  connect() {
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

    document.addEventListener("turbo:before-fetch-response", (event) => {
      const fetchResponse = event.detail.fetchResponse;

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

    document.addEventListener("turbo:frame-load", (event) => {
      const loading = document.getElementById("turbo-loading");

      if (loading) {
        document.getElementById("turbo-loading").classList.remove("!flex");
      }
    });
  }

  disconnect() {
    document.removeEventListener("turbo:click", () => {});
    document.removeEventListener("turbo:submit-start", () => {});
    document.removeEventListener("turbo:before-fetch-response", () => {});
    document.removeEventListener("turbo:frame-load", () => {});
    document.removeEventListener("turbo:before-fetch-request", async () => {});
  }
}
