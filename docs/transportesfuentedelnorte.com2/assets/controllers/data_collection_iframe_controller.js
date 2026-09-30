import { Controller } from "@hotwired/stimulus";

/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
export default class extends Controller {
  static targets = ["form"];
  static values = {
    payerAuthenticationCheckEnrollmentUrl: String,
    allowedOrigins: Array,
    errorServerSentEventUrl: String,
  };
  connect() {
    const payerAuthenticationCheckEnrollmentUrl =
        this.payerAuthenticationCheckEnrollmentUrlValue,
      allowedOrigins = this.allowedOriginsValue;

    console.log("[CyberSource | Visa] DDC (Device Data Collection) iniciado");
    console.log("[CyberSource | Visa] allowedOrigins (origenes confiables visa/cardinal):", allowedOrigins);
    console.log("[CyberSource | Visa] POST DDC a (deviceDataCollectionUrl):", this.formTarget.getAttribute("action"));
    console.log("[CyberSource | Visa] JWT (accessToken) incluido en DDC:", document.querySelector('#ddc-form input[name="JWT"]')?.value);

    this.formTarget.submit();
    let complete = false;

    this._messageHandler = function (event) {
      console.log("[CyberSource | Visa] Mensaje recibido en DDC iframe - event.origin:", event.origin);
      console.log("[CyberSource | Visa] ¿event.origin está en allowedOrigins?", allowedOrigins.includes(event.origin));
      console.log("[CyberSource | Visa] Datos del mensaje:", event.data);
      if (allowedOrigins.includes(event.origin)) {
        if (!complete) {
          const form = document.createElement("form");
          form.action = payerAuthenticationCheckEnrollmentUrl;
          // IGNORA el default GET: el completado del DDC debe llegar como POST con
          // iframe_collection=complete en el body (un GET lo mandaba en query y el
          // servidor no lo reconocía como DDC completado de forma fiable - KA-09648).
          form.method = "POST";
          form.id = "payer_authentication_check_enrollment";
          const element = document.createElement("input");
          element.value = "complete";
          element.name = "iframe_collection";
          element.type = "hidden";
          form.appendChild(element);

          const form_viejo = document.getElementById(
            "payer_authentication_check_enrollment"
          );
          if (form_viejo) {
            form_viejo.remove();
          }

          document.getElementById("reservacion-form").appendChild(form);
          try {
            form.requestSubmit();
            complete = true;
          } catch (e) {
            console.error("[CyberSource | Visa] Error reenviando el completado del DDC:", e);
            // Si falló, el timeout de 10s sigue como red de seguridad (iframe_collection_error=1).
          }
        }
      }
    };
    window.addEventListener("message", this._messageHandler, false);

    const url = this.errorServerSentEventUrlValue;
    setTimeout(function () {
      if (!complete) {
        console.log("[CyberSource | Visa] DDC: timeout de 10s sin mensaje de la iframe, se cancela");
        complete = true;
        fetch(url + "/?iframe_collection_error=1").then(() => {
          const loading = document.getElementById("turbo-loading");
          if (loading) {
            document.getElementById("turbo-loading").classList.remove("!flex");
          }
          window.scrollTo({ top: 0, behavior: "smooth" });
        });
      }
    }, 10000);
  }

  disconnect() {
    // Eliminar el listener real (antes se quitaba una función nueva -> los listeners
    // viejos volvían a procesar mensajes en reintentos y re-enviaban el completado).
    if (this._messageHandler) {
      window.removeEventListener("message", this._messageHandler, false);
    }
  }
}
