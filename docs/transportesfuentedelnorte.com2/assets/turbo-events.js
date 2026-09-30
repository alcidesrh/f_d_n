import * as Turbo from "@hotwired/turbo";
import { spinner } from "./util";

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

document.addEventListener("turbo:before-fetch-request", async function (event) {
  document.removeEventListener("typed-stop", _onStopTyped);

  // const frameId = event.detail.fetchOptions.headers["Turbo-Frame"];

  // if (
  //   frameId &&
  //   !document.getElementById(frameId).dataset.noloading &&
  //   !event.target.dataset.noloading
  // ) {
  //   const loading = document.getElementById("turbo-loading");
  //   if (loading) {
  //     document.getElementById("turbo-loading").classList.add("!flex");
  //   }
  // }
});

document.addEventListener("turbo:before-fetch-response", (event) => {
  const fetchResponse = event.detail.fetchResponse;

  document.addEventListener("typed-stop", _onStopTyped);

  if (fetchResponse.response.headers.get("session-terminada")) {
    event.preventDefault();
    Turbo.clearCache();
    Turbo.visit(fetchResponse.response.headers.get("Turbo-Location"));
  } else if (fetchResponse.response.headers.get("procesando-pago")) {
    event.preventDefault();
  } else if (fetchResponse.response.headers.get("error-pago")) {
    event.preventDefault();

    document.querySelectorAll(".spinner-wrap").forEach((element) => {
      element.remove();
    });
    document.querySelectorAll(".spinner-temp").forEach((element) => {
      element.classList.remove("spinner-temp");
      element.classList.remove("hidden");
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

function _onStopTyped({ detail: { typed } }) {
  typed.stop();
}
document.addEventListener("turbo:frame-load", (event) => {
  const loading = document.getElementById("turbo-loading");

  if (loading) {
    document.getElementById("turbo-loading").classList.remove("!flex");
  }
});
