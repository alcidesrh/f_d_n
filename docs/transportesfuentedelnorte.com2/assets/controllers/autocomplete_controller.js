import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  tomSelect = null;
  value = null;
  initialize() {
    this._onPreConnect = this._onPreConnect.bind(this);
    this._onConnect = this._onConnect.bind(this);
  }

  connect() {
    this.element.addEventListener(
      "autocomplete:pre-connect",
      this._onPreConnect
    );
    this.element.addEventListener("autocomplete:connect", this._onConnect);
    this.value = this.element.value;
  }

  disconnect() {
    // You should always remove listeners when the controller is disconnected to avoid side-effects
    this.element.removeEventListener(
      "autocomplete:pre-connect",
      this._onConnect
    );
    this.element.removeEventListener(
      "autocomplete:connect",
      this._onPreConnect
    );
  }

  _onPreConnect(event) {
    // TomSelect has not been initialized - options can be changed
    event.detail.options.allowEmptyOption = true;
    event.detail.options.onChange = (value) => {
      if (value) {
        this.tomSelect.blur();
      }
    };
  }

  _onConnect(event) {
    event.detail.options.hidePlaceholder = true;
    event.detail.options.allowEmptyOption = true;
    this.tomSelect = event.detail.tomSelect;
  }

  provincia(event = null) {
    if (event.currentTarget.value == 233 || event.currentTarget.value == 39) {
      document.querySelector("#codigo_postal").classList.remove("hidden");
      document
        .querySelector("#codigo_postal")
        .setAttribute("required", "required");
    } else {
      document.querySelector("#codigo_postal").classList.add("hidden");
      document.querySelector("#codigo_postal").removeAttribute("required");
    }
    if (this.value) {
      this.ciudad();
    }
    const frame = document.getElementById("provincia-frame");
    frame.src = frame.dataset.provinciasRuta + "/" + event.currentTarget.value;
    this.value = event.currentTarget.value;
  }

  ciudad(event) {
    const frame = document.getElementById("ciudad-frame");
    frame.src =
      frame.dataset.municipiosRuta +
      "/" +
      (event ? event.currentTarget.value : "?reset=1");
  }
}
