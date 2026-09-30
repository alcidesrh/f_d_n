import { Controller } from "@hotwired/stimulus";
import Typed from "typed.js";

export default class extends Controller {
  static values = {
    strings: Array,
    typeSpeed: { type: Number, default: 30 },
    smartBackspace: { type: Boolean, default: true },
    startDelay: Number,
    backSpeed: Number,
    shuffle: Boolean,
    backDelay: { type: Number, default: 700 },
    fadeOut: Boolean,
    fadeOutClass: { type: String, default: "typed-fade-out" },
    fadeOutDelay: { type: Number, default: 500 },
    loop: Boolean,
    loopCount: { type: Number, default: Infinity },
    showCursor: { type: Boolean, default: true },
    cursorChar: { type: String, default: "" },
    autoInsertCss: { type: Boolean, default: true },
    attr: String,
    bindInputFocusEvents: Boolean,
    contentType: { type: String, default: "html" },
  };
  typed = null;
  connect() {
    const options = {
      strings: this.stringsValue,
      typeSpeed: this.typeSpeedValue,
      smartBackspace: this.smartBackspaceValue,
      startDelay: this.startDelayValue,
      backSpeed: this.backSpeedValue,
      shuffle: this.shuffleValue,
      backDelay: this.backDelayValue,
      fadeOut: this.fadeOutValue,
      fadeOutClass: this.fadeOutClassValue,
      fadeOutDelay: this.fadeOutDelayValue,
      loop: this.loopValue,
      loopCount: this.loopCountValue,
      showCursor: this.showCursorValue,
      cursorChar: this.cursorCharValue,
      autoInsertCss: this.autoInsertCssValue,
      attr: this.attrValue,
      bindInputFocusEvents: this.bindInputFocusEventsValue,
      contentType: this.contentTypeValue,
    };

    options.backDelay = 2000;
    options.smartBackspace = true;
    options.startDelay = 1000;
    options.backSpeed = 20;
    options.showCursor = false;

    options.onStop = (t) => {
      setTimeout(function () {
        const type_placeholder = document.getElementById("typed-placeholder");
        if (type_placeholder) {
          type_placeholder.classList.remove("hidden");
        }
        // const typed_bg = document.getElementById("typed-bg");
        // if (typed_bg) {
        //   typed_bg.classList.add("bg-slate-800/95");
        // }
      }, 1000);
    };

    options.onComplete = (t) => {
      setTimeout(() => {
        this.typed.destroy();

        const type_placeholder = document.getElementById("typed-placeholder");
        if (type_placeholder) {
          type_placeholder.classList.remove("hidden");
          this.typed.destroy();
        }
        // const typed_bg = document.getElementById("typed-bg");
        // if (typed_bg) {
        //   typed_bg.classList.add("bg-slate-800/95");
        // }
      }, 1000);
    };

    this.typed = new Typed(this.element, options);

    const event = new CustomEvent("typed-stop", {
      detail: { typed: this.typed },
    });
    document.dispatchEvent(event);
  }
}
