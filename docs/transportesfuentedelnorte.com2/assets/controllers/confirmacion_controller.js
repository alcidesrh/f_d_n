import { Controller } from "@hotwired/stimulus";
/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = ["pdf_link"];

  connect() {
    window.scrollTo(0, 0);

    this.dispatch("reserva", { detail: { paso: 4 } });

    if (this.hasPdf_linkTarget) {
      this.pdf_linkTarget.click();
    }

    this.dispatch("paso_completado", { detail: { paso: 4 } });
  }
}
