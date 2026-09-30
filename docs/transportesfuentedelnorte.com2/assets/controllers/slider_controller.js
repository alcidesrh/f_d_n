import { Controller } from "@hotwired/stimulus";
import { gsap } from "gsap";
/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
export default class extends Controller {
  static targets = ["slider"];
  static values = {
    images: String,
  };
  count = 1;
  images = [];
  slider = null;
  connect() {
    if (!this.hasSliderTarget) {
      return;
    }
    this.slider = gsap.timeline({ repeat: -1, yoyo: true, delay: 3 }); //, delay: 15
    for (let i = 0; i < this.sliderTarget.children.length; i++) {
      this.slider.to(this.sliderTarget.children[i], {
        opacity: 0,
        duration: 4,
        delay: 4,
      });
    }
    this.slider.play();
  }
  control({ detail: { stop } }) {
    this.slider.paused(stop);
  }
}
