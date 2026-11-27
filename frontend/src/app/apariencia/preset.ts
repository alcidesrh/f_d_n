/**
 * Preset de PrimeVue de la apariencia, en capas:
 *
 *   1. El preset base del `estilo` (Aura, Lara, Material o Nora; ~140 KB cada
 *      uno, se baja solo el que se usa) con su esquema claro.
 *   2. Los colores: las rampas de Tailwind como primitivas, la del `primario` y
 *      la de la `superficie`, y los tokens semánticos de texto, contenido,
 *      navegación y overlays. En `dark` se invierten todas las rampas (50 ↔ 950):
 *      PrimeVue no tiene modo oscuro propio (`darkModeSelector: false`).
 *   3. Los ajustes por componente (`componentes.ts`).
 *   4. `conectarParametros`: radios, rellenos, sombras y duraciones literales
 *      pasan a depender de los parámetros CSS de apariencia
 *      (`styles/tema/apariencia.css`). Con los de fábrica (1) miden lo mismo
 *      que el preset; cambiarlos no regenera este CSS.
 *
 * Solo el modo, los colores y el estilo regeneran el preset (`CLAVES_DEL_PRESET`);
 * el contraste es un parámetro más (`contrastado`, en los colores de texto y bordes).
 */
import type { Preset } from '@primeuix/themes/types'
import colors from 'tailwindcss/colors'
import type { Ajustes, Estilo } from './ajustes'
import { componentesPreset } from './componentes'
import { completarConBase, contrastado, esObjeto, invertirPaleta, invertirRampa, masMarcado, resolverReferencia, type Tokens } from './tokens'

const CARGAR_ESTILO: Record<Estilo, () => Promise<{ default: Preset }>> = {
  material: () => import('@primeuix/themes/material'),
  aura: () => import('@primeuix/themes/aura'),
  lara: () => import('@primeuix/themes/lara'),
  nora: () => import('@primeuix/themes/nora'),
}

type Rampa = Record<string, string>
const rampa = (nombre: string): Rampa => (colors as unknown as Record<string, Rampa>)[nombre] ?? {}

export type AjustesDelPreset = Pick<Ajustes, 'estilo' | 'primario' | 'superficie' | 'modo'>

/** El preset para `usePreset`: base + colores + componentes, conectado a los parámetros CSS. */
export async function presetCompleto({ estilo, primario, superficie, modo }: AjustesDelPreset): Promise<Tokens> {
  const base = (await (CARGAR_ESTILO[estilo] ?? CARGAR_ESTILO.lara)()).default as Tokens
  // El esquema claro del base en el primer nivel; su versión oscura no se usa.
  const baseClaro = { ...base, semantic: { ...base.semantic, colorScheme: base.semantic.colorScheme.light } }
  const bordeDelCampo = baseClaro.semantic.colorScheme?.formField?.borderColor
  return conectarParametros(completarConBase(baseClaro, coloresDelTema(primario, superficie, modo, bordeDelCampo)))
}

/**
 * Lo que la app cambia del preset base: colores (en claro u oscuro) y
 * componentes. `bordeDelCampo`: el del estilo base (cada uno trae el suyo),
 * que con contraste alto se marca dos pasos más.
 */
export function coloresDelTema(primario: string, superficie: string, modo: Ajustes['modo'], bordeDelCampo = '{surface.300}'): Tokens {
  const oscuro = modo === 'dark'
  return {
    primitive: oscuro ? invertirPaleta(colors) : colors,
    semantic: {
      formField: { borderRadius: '4px' },
      transitionDuration: '.2s',
      // Una sola rampa: `invertirPaleta` invierte las rampas que contiene, no la rampa misma.
      primary: oscuro ? invertirRampa(rampa(primario)) : rampa(primario),
      colorScheme: {
        surface: oscuro ? { 0: '#000000', ...invertirRampa(rampa(superficie)) } : { 0: '#ffffff', ...rampa(superficie) },
        primary: {
          highlight: '{primary.50}',
          color: '{primary.700}',
          contrastColor: '{primary.50}',
          hoverColor: '{primary.600}',
          activeColor: '{primary.700}',
        },
        highlight: {
          background: '{surface.200}',
          hover: '{surface.100}',
          focusBackground: '{surface.100}',
          color: '{surface.700}',
          focusColor: '{surface.300}',
        },
        formField: {
          borderColor: contrastado(bordeDelCampo, masMarcado(bordeDelCampo)),
        },
        // Con contraste alto, el texto y los bordes un tono (o dos) más cerca del extremo.
        text: {
          color: contrastado('{surface.800}', '{surface.950}'),
          hoverColor: contrastado('{surface.800}', '{surface.950}'),
          mutedColor: contrastado('{surface.500}', '{surface.700}'),
          hoverMutedColor: contrastado('{surface.600}', '{surface.800}'),
        },
        content: {
          background: '{surface.0}',
          hoverBackground: '{surface.100}',
          borderColor: contrastado('{surface.300}', '{surface.400}'),
          color: '{text.color}',
          hoverColor: '{text.hover.color}',
        },
        navigation: {
          item: {
            focusBackground: '{surface.200}',
            activeBackground: '{surface.200}',
            color: '{text.color}',
            focusColor: '{text.hover.color}',
            activeColor: '{text.hover.color}',
            icon: {
              color: '{surface.400}',
              inactive: '{surface.200}',
              focusColor: '{surface.500}',
              activeColor: '{surface.500}',
            },
          },
          submenuLabel: { background: 'transparent', color: '{text.color}' },
          submenuIcon: { color: '{surface.400}', focusColor: '{surface.500}', activeColor: '{surface.500}' },
        },
        overlay: {
          select: { background: '{surface.0}', borderColor: '{surface.300}', color: '{text.color}' },
          popover: { background: '{surface.100}', borderColor: '{surface.300}', color: '{text.color}' },
          modal: { background: '{surface.100}', borderColor: '{surface.300}', color: '{text.color}' },
        },
      },
    },
    components: componentesPreset(),
  }
}

//#region Parámetros CSS

/** Una longitud literal (`0.75rem`, `5px`, `.5rem`), sin referencias. */
const LONGITUD = /^-?(\d+\.?\d*|\.\d+)(px|rem|em)$/
const DURACION = /^(\d+\.?\d*|\.\d+)(s|ms)$/

/** Cada longitud de `valor` (p. ej. `0.5rem 1rem`) multiplicada por el parámetro; `0` y las referencias quedan. */
function escalarLongitudes(valor: string, parametro: string, admite: (parte: string) => boolean = () => true): string {
  return valor
    .trim()
    .split(/\s+/)
    .map((parte) => (LONGITUD.test(parte) && parseFloat(parte) !== 0 && admite(parte) ? `calc(${parte} * var(${parametro}))` : parte))
    .join(' ')
}

/** Radios de forma (no pastillas ni círculos): hasta 12px o 0.75rem. Los mayores son formas a propósito (chips, interruptores). */
const esRadioDeForma = (parte: string) => parseFloat(parte) <= (parte.endsWith('px') ? 12 : 0.75)

/** La opacidad de cada `rgba()`/`rgb()` de una sombra, multiplicada por el parámetro «Sombras». */
function escalarSombra(valor: string): string {
  return valor.replace(/rgba?\(([^)]*)\)/g, (color, cuerpo: string) => {
    const partes = cuerpo.split(/[\s,/]+/).filter(Boolean)
    if (partes.length !== 4) return color
    const [r, g, b, a] = partes
    return `rgb(${r} ${g} ${b} / calc(${a} * var(--apariencia-sombras)))`
  })
}

/**
 * `preset` con sus medidas literales conectadas a los parámetros de apariencia:
 *  - radios (la escala `primitive.borderRadius` y los literales chicos) × `--apariencia-esquinas`;
 *  - rellenos (`padding`, `paddingX`, `paddingY`) × `--apariencia-densidad`, y el ancho de los
 *    botones de solo ícono crece lo mismo que su alto, para seguir cuadrados;
 *  - opacidad de las sombras × `--apariencia-sombras`;
 *  - duraciones (`transitionDuration`) × `--apariencia-movimiento`.
 * Las referencias (`{border.radius.md}`) ya siguen a su origen. No muta `preset`.
 */
export function conectarParametros(preset: Tokens): Tokens {
  const anchos = anchosDeSoloIcono(preset)
  const transformar = (nodo: Tokens, ruta: string[]): Tokens => {
    const out: Tokens = {}
    for (const [clave, valor] of Object.entries(nodo)) {
      const aqui = [...ruta, clave]
      const ancho = anchos.get(aqui.join('.'))
      if (esObjeto(valor)) out[clave] = transformar(valor, aqui)
      else if (ancho) out[clave] = ancho
      else if (typeof valor !== 'string' || valor.includes('{')) out[clave] = valor
      else out[clave] = conectar(clave, valor, aqui)
    }
    return out
  }
  return transformar(preset, [])
}

function conectar(clave: string, valor: string, ruta: string[]): string {
  if (ruta[0] === 'primitive' && ruta[1] === 'borderRadius') return escalarLongitudes(valor, '--apariencia-esquinas')
  if (/radius$/i.test(clave) && !/rounded/i.test(clave)) return escalarLongitudes(valor, '--apariencia-esquinas', esRadioDeForma)
  if (/^padding[XY]?$/.test(clave)) return escalarLongitudes(valor, '--apariencia-densidad')
  if (/shadow$/i.test(clave)) return escalarSombra(valor)
  if (clave === 'transitionDuration' && DURACION.test(valor.trim())) return `calc(${valor.trim()} * var(--apariencia-movimiento))`
  return valor
}

/**
 * El botón de solo ícono es cuadrado: ancho fijo (`iconOnlyWidth`) y alto del
 * relleno vertical. Con la densidad, el alto cambia en 2 × relleno × (d − 1):
 * el ancho, lo mismo. Ruta del token → valor nuevo.
 */
function anchosDeSoloIcono(preset: Tokens): Map<string, string> {
  const out = new Map<string, string>()
  const raiz = preset.components?.button?.root
  if (!esObjeto(raiz)) return out
  for (const [tamano, nodo] of [['', raiz], ['sm', raiz.sm], ['lg', raiz.lg]] as const) {
    if (!esObjeto(nodo)) continue
    const ancho = resolverReferencia(preset, nodo.iconOnlyWidth)
    const relleno = resolverReferencia(preset, nodo.paddingY)
    if (typeof ancho !== 'string' || typeof relleno !== 'string' || !LONGITUD.test(ancho) || !LONGITUD.test(relleno)) continue
    const ruta = ['components', 'button', 'root', ...(tamano ? [tamano] : []), 'iconOnlyWidth'].join('.')
    out.set(ruta, `calc(${ancho} + 2 * ${relleno} * (var(--apariencia-densidad) - 1))`)
  }
  return out
}
//#endregion
