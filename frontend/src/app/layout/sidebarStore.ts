/**
 * Stores de los paneles laterales (`left`, `right` o uno por panel de ruta):
 * modo `open`/`mini`/`close` persistido y animaciones GSAP del shell.
 */
import { defineStore } from 'pinia'
import { gsap } from 'gsap'
import { closeFlyout, openFlyout, resetFlyouts } from './sidebarFlyout'

export type SidebarMode = 'open' | 'mini' | 'close'

export interface SidebarStoreState {
  side: 'left' | 'right'
  mode: SidebarMode
  prevMode: SidebarMode
  open: number
  mini: number
  close: number
}

function createDefinition(side: 'left' | 'right', name: string) {
  return defineStore(name, {
    persist: true,
    state: (): SidebarStoreState => ({
      side: side,
      mode: 'open',
      prevMode: 'mini',
      open: 250,
      mini: 71,
      close: 0,
    }),
    getters: {
      width: (s: SidebarStoreState): number =>
        ({ open: s.open, mini: s.mini, close: s.close })[s.mode],
    },
    actions: {
      setMode(mode?: SidebarMode) {
        if (mode && mode != this.mode) {
          this.prevMode = this.mode
          this.mode = mode
        } else if (this.prevMode != this.mode) {
          mode = this.mode
          this.mode = this.prevMode
          this.prevMode = mode
        } else {
          this.mode = this.mode == 'open' ? 'mini' : 'open'
          this.prevMode = this.mode == 'open' ? 'mini' : 'open'
        }
      },
      /** En `mini`, el enlace se despliega mostrando su texto mientras dure el hover. */
      handleMouseEnter(e: MouseEvent) {
        if (this.mode !== 'mini') return
        openFlyout(e.currentTarget as HTMLElement, {
          side: this.side,
          mini: this.mini,
          open: this.open,
        })
      },
      handleMouseLeave(e: MouseEvent) {
        closeFlyout(e.currentTarget as HTMLElement)
      },
      sidebarUpdate() {
        resetFlyouts(this.side)
        const targets = {
          sidebar: `.sidebar.${this.side}`,
          main: `.main`,
          menu: document.querySelectorAll(`.sidebar.${this.side} .menu-text`),
        }
        const duration = 0.2
        // const ease = "expoScale(1, 2)";
        const ease = 'expoScale(0.5,7, none)'

        if (this.mode === 'open') {
          gsap.to(targets.sidebar, { width: this.width, duration, ease })
          if (this.side == 'left') {
            gsap.to(targets.main, {
              marginLeft: this.width,
              duration,
              ease: ease,
            })
          } else {
            gsap.to(targets.main, {
              marginRight: this.width,
              duration,
              ease,
            })
          }
          // Una barra sin ítems (sin menús asignados) no tiene textos que animar.
          if (targets.menu.length)
            gsap.to(targets.menu, {
              opacity: 1,
              duration: duration * 0.8,
              ease,
            })
        } else if (this.mode === 'mini') {
          gsap.to(targets.sidebar, {
            width: this.width,
            overflow: 'visible',
            duration,
            ease,
          })

          if (this.side == 'left') {
            gsap.to(targets.main, { marginLeft: this.width, duration, ease })
          } else {
            gsap.to(targets.main, {
              marginRight: this.width,
              duration,
              ease,
            })
          }
          // Una barra sin ítems (sin menús asignados) no tiene textos que animar.
          if (targets.menu.length)
            gsap.to(targets.menu, {
              opacity: 0,
              duration: duration * 0.5,
              ease,
            })
        } else if (this.mode === 'close') {
          gsap.to(targets.sidebar, {
            width: this.width,
            opacity: 1,
            overflow: 'hidden',
            duration,
            ease,
          })
          if (this.side == 'left') {
            gsap.to(targets.main, { marginLeft: 0, duration, ease: ease })
          } else {
            gsap.to(targets.main, { marginRight: 0, duration, ease })
          }
          // Una barra sin ítems (sin menús asignados) no tiene textos que animar.
          if (targets.menu.length)
            gsap.to(targets.menu, {
              opacity: 0,
              duration: duration * 0.5,
              ease,
            })
        }
      },
    },
  })
}

type SidebarDefinition = ReturnType<typeof createDefinition>
export type SidebarStore = ReturnType<SidebarDefinition>

const definitions = new Map<string, SidebarDefinition>()

/** Definición (cacheada: Pinia no admite ids repetidos) del store del panel `name`. */
export function defineSidebarStore(side: 'left' | 'right', name: string = side): SidebarDefinition {
  let definition = definitions.get(name)
  if (!definition) {
    definition = createDefinition(side, name)
    definitions.set(name, definition)
  }
  return definition
}
