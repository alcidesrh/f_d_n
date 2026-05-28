import { reactive } from "vue";
import { breakpoints } from "src/config/breakpoints";
import { RefSymbol } from "@vue/reactivity";

export const isMobile = ref();
export const isTablet = ref();
export const isDesktop = ref();

class ResponsiveService {
  state = reactive({
    width: window.innerWidth,
    height: window.innerHeight,
    name: "md",
    isMobile: false,
    isTablet: false,
    isDesktop: true,
  });

  constructor() {
    this.update();
    window.addEventListener("resize", this.update);
  }

  update = () => {
    const w = window.innerWidth;

    this.state.width = w;
    this.state.height = window.innerHeight;

    if (w < breakpoints.sm) {
      this.state.name = "xs";
    } else if (w < breakpoints.md) {
      this.state.name = "sm";
    } else if (w < breakpoints.lg) {
      this.state.name = "md";
    } else if (w < breakpoints.xl) {
      this.state.name = "lg";
    } else {
      this.state.name = "xl";
    }

    isMobile.value = this.state.isMobile = w < breakpoints.sm;
    isTablet.value = this.state.isTablet =
      w >= breakpoints.sm && w < breakpoints.lg;
    isDesktop.value = this.state.isDesktop = w >= breakpoints.lg;

    // document.body.classList.remove(
    //   "screen-xs",
    //   "screen-sm",
    //   "screen-md",
    //   "screen--lg",
    //   "screen-xl",
    // );

    // document.body.classList.add(`screen-${this.state.name}`);
  };
}

export const responsiveService = new ResponsiveService();
