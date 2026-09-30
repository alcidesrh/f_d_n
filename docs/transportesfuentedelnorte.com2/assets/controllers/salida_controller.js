import { Controller } from "@hotwired/stimulus";

/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static values = {
    serverResponse: String,
  };
  static targets = ["form"];

  connect() {

    window.scrollTo({ top: 0, behavior: "smooth" });

    this.dispatch("paso_completado", { detail: { paso: 1 } });

  }
}
