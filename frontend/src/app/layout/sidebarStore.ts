/**
 * Stores de los paneles laterales (`left`, `right` o uno por panel de ruta):
 * modo `open`/`mini`/`close` persistido y animaciones GSAP del shell.
 */
import { defineStore } from 'pinia'
import { gsap } from 'gsap'

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
      handleMouseLeave(e: MouseEvent) {
        if (this.mode === 'mini') {
          const to: gsap.TweenVars = {
            border: 'none',
            borderRadius: 'none',
            ease: 'power1.out',
            zIndex: 999,
            width: 'auto',
            duration: 0.4,
            boxShadow: 'none',
          }
          if (this.side == 'left') {
            gsap.to(e.target, to)
          } else {
            to.x = 0
            gsap.to(e.target, to)
          }
        } else if (this.mode === 'open') {
          // gsap.to(link, { backgroundColor: bg });
        }
      },
      handleMouseEnter(e: MouseEvent) {
        const rootStyles = window.getComputedStyle(document.documentElement)
        const shadow = rootStyles.getPropertyValue('--p-surface-300')

        if (this.mode === 'mini') {
          const temp: gsap.TweenVars = {
            borderRadius: '0 8px 8px 0',
            duration: 0.25,
            ease: 'power1.out',
            zIndex: 999,
            width: '0px',
          }
          if (this.side == 'left') {
            gsap.fromTo(
              e.target,
              { ...temp },
              {
                duration: 0.4,
                width: 200,
                boxShadow: `1px 0px 3px ${shadow}`,
              },
            )
          } else {
            temp.flexDirection = 'row-reverse'
            gsap.fromTo(
              e.target,
              {
                ...temp,
                borderRadius: '8 0px 0px 8',
                display: 'flex',
                width: 200,
                flexDirection: 'row-reverse',
                x: -130,
                justifyContent: 'end',
              },
              {
                duration: 0.4,

                // x: -130,
                boxShadow: `-1px 0px 3px ${shadow}`,
              },
            )
          }

          gsap.fromTo(
            (e.currentTarget as HTMLElement).querySelector('.menu-text'),
            { opacity: 1, width: '0px', overflow: 'hidden' },
            { width: '100%', duration: 0.4 },
          )
        }
      },
      sidebarUpdate() {
        const targets = {
          sidebar: `.sidebar.${this.side}`,
          main: `.main`,
          menu: `.sidebar.${this.side} .menu-text`,
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
