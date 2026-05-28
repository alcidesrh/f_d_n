import { inject } from "vue";

export function useBreakpoint() {
  const responsive = inject("responsive");

  return responsive;
}
