/**
 * Breakpoints del layout (rem, min-width, mobile-first). Mismos valores que
 * `--breakpoint-*` en `assets/tokens.css`; ver docs/frontend/responsive.md.
 *
 * Solo para diferencias de comportamiento que CSS no puede resolver (p. ej.
 * el shell: drawer debajo de `lg`, sidebar fijo desde `lg`). Lo visual va
 * en CSS: clases `md:`/`lg:`/`xl:` o `@container`.
 */
export const BREAKPOINTS = { md: 48, lg: 64, xl: 80 } as const

export type Breakpoint = keyof typeof BREAKPOINTS

/** Media query "desde `breakpoint`": `(width >= 64rem)`. */
export function mediaUp(breakpoint: Breakpoint): string {
  return `(width >= ${BREAKPOINTS[breakpoint]}rem)`
}
