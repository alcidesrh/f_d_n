import { Controller } from "@hotwired/stimulus";

/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */

export default class extends Controller {
  static values = {
    challengeResponse: String,
    authenticationValidationRoute: String,
    errorUrl: String,
  };
  connect() {
    const stepUpForm = document.querySelector("#step-up-form");
    if (stepUpForm) {
      console.log("[CyberSource | Visa] Step-up (challenge 3DS) iniciado");
      console.log("[CyberSource | Visa] POST Step-up a (stepUpUrl):", stepUpForm.getAttribute("action"));
      console.log("[CyberSource | Visa] JWT (accessToken) incluido en Step-up:", document.querySelector('#step-up-form input[name="JWT"]')?.value);

      stepUpForm.submit();

      document.getElementById("reserva-wrap")?.classList.remove("reserva");

      const loading = document.getElementById("turbo-loading");
      if (loading) {
        document.getElementById("turbo-loading").classList.remove("!flex");
      }

      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    const eventSource = new EventSource(this.challengeResponseValue);
    let resuelto = false;

    // Anti carga infinita: si el banco/ACS no devuelve la respuesta del challenge en
    // 3 minutos (el único feedback del flujo es ese evento Mercure), mostrar un error
    // claro en vez de dejar la página cargando para siempre.
    window.setTimeout(() => {
      if (!resuelto) {
        eventSource.close();
        this.mostrarError(
          "El banco no confirmó la operación en el tiempo esperado. Por favor reintente el pago."
        );
      }
    }, 180000);

    eventSource.onmessage = (event) => {
      console.log("[CyberSource | Visa] Respuesta del challenge (ACS/ReturnURL) recibida vía Mercure:", event.data);
      resuelto = true;
      document.getElementById("reserva-wrap")?.classList.add("reserva");

      const loading = document.getElementById("turbo-loading");
      if (loading) {
        document.getElementById("turbo-loading").classList.add("!flex");
      }
      document.querySelector("#authentication_check_enrollment>*")?.remove();

      let response;
      try {
        response = JSON.parse(event.data);
      } catch (e) {
        console.error("[CyberSource | Visa] Respuesta del ACS no parseable como JSON:", event.data, e);
        this.mostrarError(
          "La respuesta del banco no pudo procesarse. Por favor reintente el pago."
        );
        return;
      }

      const form = document.createElement("form");
      form.action = this.authenticationValidationRouteValue;
      form.method = "POST";
      form.id = "payer-authentication-validation-form";

      if (response && typeof response === "object") {
        for (let key in response) {
          const element = document.createElement("input");
          element.value = response[key];
          element.name = key;
          element.type = "hidden";
          form.append(element);
        }
      }

      const form_viejo = document.getElementById(
        "payer-authentication-validation-form"
      );
      if (form_viejo) {
        form_viejo.remove();
      }

      document.getElementById("reservacion-form").appendChild(form);
      form.requestSubmit();

      // Si el evento llegó pero la navegación/Turbo no resolvió en 20s (respuesta sin
      // turbo-frame, error silencioso), mostrar error en vez de quedarse cargando.
      window.setTimeout(() => {
        if (resuelto && document.querySelector("#payer-authentication-validation-form")) {
          this.mostrarError(
            "El pago no pudo confirmarse. Por favor reintente el pago."
          );
        }
      }, 20000);
    };
  }

  mostrarError(texto) {
    console.error("[CyberSource | Visa]", texto);
    document.getElementById("reserva-wrap")?.classList.add("reserva");
    const loading = document.getElementById("turbo-loading");
    if (loading) {
      loading.classList.remove("!flex");
    }
    document.querySelector("#authentication_check_enrollment>*")?.remove();
    const contenedor = document.getElementById("error_pago");
    if (contenedor && !contenedor.querySelector("#error-pago")) {
      contenedor.innerHTML =
        '<div class="text-center mt-5 mb-10" id="error-pago"><div class="rounded-lg border border-red-300 bg-red-50 text-red-700 font-medium p-4">' +
        texto +
        "</div></div>";
    }
    // Notificar al servidor (errorPago) una sola vez: concluye la marca 'pago en
    // curso' y permite al cliente reintentar sin esperar la ventana de 15 minutos.
    if (!this._errorNotificado) {
      this._errorNotificado = true;
      if (this.errorUrlValue) {
        fetch(this.errorUrlValue + "/?challenge_timeout=1").catch(() => {});
      }
    }
  }
}