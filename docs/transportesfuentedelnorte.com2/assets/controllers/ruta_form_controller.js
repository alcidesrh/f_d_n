import { Controller } from "@hotwired/stimulus";
/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
  connect() {
    this.dispatch("paso_completado", { detail: { paso: 0 } });
    window.scrollTo({ top: 0, behavior: "smooth" });
  }
}
