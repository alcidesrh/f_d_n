import Aura from "@primeuix/themes/aura";
import Lara from "@primeuix/themes/lara";
import Material from "@primeuix/themes/material";
import Nora from "@primeuix/themes/nora";
import type { Preset } from "@primeuix/themes/types";
import type { PrimaryColor, SurfacePalette, ThemeMode, ThemePreset } from "./themeTypes";
import colors from "tailwindcss/colors";

const RAMP_STEPS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as const;

const isRamp = (value: unknown): value is Record<string, string> =>
  !!value && typeof value === "object" && RAMP_STEPS.every((step) => typeof (value as Record<string, unknown>)[step] === "string");

/** Reverses one color ramp (50 ↔ 950, 100 ↔ 900, …; 500 stays). */
export function invertRamp(ramp: Record<string, string>): Record<string, string> {
  const reversed: Record<string, string> = {};
  RAMP_STEPS.forEach((step, i) => {
    reversed[String(step)] = ramp[RAMP_STEPS[RAMP_STEPS.length - 1 - i]!]!;
  });
  return reversed;
}

/** Reverses every color ramp of a palette (`{ red: {50…950}, … }`); other entries stay. */
export function invertPalette<T>(palette: T): T {
  const out: Record<string, unknown> = {};
  for (const [name, value] of Object.entries(palette as object)) out[name] = isRamp(value) ? invertRamp(value) : value;
  return out as T;
}

export interface PrimaryOption {
  key: PrimaryColor;
  label: string;
  swatch: string;
}

export interface SurfaceOption {
  key: SurfacePalette;
  label: string;
  bg: string;
  accent: string;
}

export interface PresetOption {
  key: ThemePreset;
  label: string;
  value: Preset;
}

/** PrimeVue theme presets selectable in the customizer, as in Sakai. */
export const PRESET_OPTIONS = [
  { key: "material", label: "Material", value: Material },
  { key: "aura", label: "Aura", value: Aura },
  { key: "lara", label: "Lara", value: Lara },
  { key: "nora", label: "Nora", value: Nora },
] as const satisfies readonly PresetOption[];

/** Options shown in the header's appearance / theme customizer. */
export const PRIMARY_OPTIONS: PrimaryOption[] = [
  { key: "neutral", label: "Neutral", swatch: "oklch(0.44 0.00 0)" },
  { key: "stone", label: "Stone", swatch: "oklch(0.44 0.01 74)" },
  { key: "gray", label: "Gris", swatch: "oklch(0.45 0.03 257)" },
  { key: "slate", label: "Slate", swatch: "oklch(0.45 0.04 257)" },
  // { key: "zinc", label: "Zinc", swatch: "oklch(0.44 0.01 286)" },

  { key: "green", label: "Verde", swatch: "oklch(0.63 0.17 149)" },
  { key: "emerald", label: "Esmeralda", swatch: "oklch(0.60 0.13 163)" },
  { key: "teal", label: "Teal", swatch: "oklch(0.60 0.10 185)" },
  { key: "cyan", label: "Cyan", swatch: "oklch(0.61 0.11 222)" },
  { key: "sky", label: "Sky", swatch: "oklch(0.59 0.14 242)" },
  { key: "blue", label: "Azul", swatch: "oklch(0.55 0.22 263)" },
  { key: "indigo", label: "Indigo", swatch: "oklch(0.51 0.23 277)" },
  { key: "violet", label: "Violeta", swatch: "oklch(0.54 0.25 293)" },
  // { key: "purple", label: "Purpura", swatch: "oklch(0.56 0.25 302)" },
  { key: "yellow", label: "Amarillo", swatch: "oklch(0.68 0.14 76)" },
  { key: "amber", label: "Ámbar", swatch: "oklch(0.67 0.16 58)" },
  { key: "orange", label: "Naranja", swatch: "oklch(0.65 0.19 41)" },
  { key: "red", label: "Rojo", swatch: "oklch(0.58 0.22 27)" },
  // { key: "taupe", label: "Taupe", swatch: "oklch(0.49 0.03 75)" }  ,
];

export const SURFACE_OPTIONS: SurfaceOption[] = [
  { key: "slate", label: "Slate", bg: "#e2e8f0", accent: "#334155" },
  { key: "gray", label: "Gray", bg: "#e5e7eb", accent: "#374151" },
  { key: "zinc", label: "Zinc", bg: "#e4e4e7", accent: "#3f3f46" },
  { key: "neutral", label: "Neutral", bg: "#e5e5e5", accent: "#404040" },
  { key: "stone", label: "Stone", bg: "#e7e5e4", accent: "#44403c" },
];

/**
 * Tokens de diseño de PrimeVue: objetos anidados libres (primitive/semantic/
 * components) que se combinan y sobrescriben por clave.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Tokens = Record<string, any>;

type Palette = Record<string, string>;
const palette = (name: string) => (colors as unknown as Record<string, Palette>)[name] ?? {};

const esObjeto = (valor: unknown): valor is Tokens => typeof valor === "object" && valor !== null && !Array.isArray(valor);

/**
 * Completa `modificaciones` con lo que `base` no sobrescribe: recursivo por
 * clave; donde ambos traen un objeto se combinan, en cualquier otro caso gana
 * la modificación. No muta ninguno de los dos.
 */
export function completarConBase(base: Tokens, modificaciones: Tokens): Tokens {
  const out: Tokens = { ...base };
  for (const [clave, valor] of Object.entries(modificaciones)) {
    const valorBase = base[clave];
    out[clave] = esObjeto(valorBase) && esObjeto(valor) ? completarConBase(valorBase, valor) : valor;
  }
  return out;
}

/** Preset base de PrimeVue (Aura, Lara…); si la clave no existe, el primero. */
function presetOf(key: ThemePreset): Tokens {
  return (PRESET_OPTIONS.find((o) => o.key === key) ?? PRESET_OPTIONS[0]).value as Tokens;
}

/**
 * Preset completo para aplicar: el base + las modificaciones de `themeColors`.
 * Como antes, el `colorScheme` del base entra en su nivel de primer nivel (el
 * claro) y no se toma su versión oscura: el modo oscuro sale de invertir rampas.
 */
export const presetCompleto = (theme: ThemePreset, primaryName: PrimaryColor, surfaceName: SurfacePalette, mode: ThemeMode): Tokens => {
  const base = presetOf(theme);
  const baseClaro = { ...base, semantic: { ...base.semantic, colorScheme: base.semantic.colorScheme.light } };
  return completarConBase(baseClaro, themeColors(primaryName, surfaceName, mode));
};

/** Solo las modificaciones sobre el preset base, en claro u oscuro (ver `presetCompleto`). */
export const themeColors = (primaryName: PrimaryColor, surfaceName: SurfacePalette, mode: ThemeMode): Tokens => {
  let primitive: Tokens;
  let primary: Palette;
  let surface: Palette;
  if (mode === "dark") {
    primitive = invertPalette(colors);
    // Una sola rampa: `invertPalette` invierte las rampas que contiene, no la rampa misma.
    primary = invertRamp(palette(primaryName));
    surface = { "0": "#000000", ...invertRamp(palette(surfaceName)) };
  } else {
    primitive = colors;
    primary = palette(primaryName);
    surface = { "0": "#ffffff", ...palette(surfaceName) };
  }
  return {
    primitive: primitive,
    semantic: {
      formField: {
        borderRadius: "4px",
      },

      transitionDuration: ".2s",
      primary: primary,
      secondary: {
        color: "{amber.500}",
        hoverColor: "{amber.600}",
        activeColor: "{amber.700}",
      },
      info: {
        color: "#06b6d4",
        hoverColor: "#0891b2",
        activeColor: "#0e7490",
      },
      colorScheme: {
        surface: surface,
        primary: {
          // color: "{primary.500}",
          // colorAccent: "{primary.700}",
          // contrastColor: "#ffffff",
          // hoverColor: "{primary.50}",
          // activeColor: "{primary.700}",
          highlight: "{primary.50}",
          color: "{primary.700}",
          contrastColor: "{primary.50}",
          hoverColor: "{primary.600}",
          activeColor: "{primary.700}",
        },

        highlight: {
          background: "{surface.200}",
          hover: "{surface.100}",
          focusBackground: "{surface.100}",
          color: "{surface.700}",
          focusColor: "{surface.300}",
        },
        text: {
          color: "{surface.700}",
          hoverColor: "{surface.800}",
          mutedColor: "{surface.500}",
          hoverMutedColor: "{surface.600}",
        },
        content: {
          background: "{surface.50}",
          hoverBackground: "{surface.200}",
          borderColor: "{surface.300}",
          color: "{text.color}",
          hoverColor: "{text.hover.color}",
        },
        navigation: {
          item: {
            focusBackground: "{surface.200}",
            activeBackground: "{surface.200}",
            color: "{text.color}",
            focusColor: "{text.hover.color}",
            activeColor: "{text.hover.color}",
            icon: {
              color: "{surface.400}",
              inactive: "{surface.200}",
              focusColor: "{surface.500}",
              activeColor: "{surface.500}",
            },
          },
          submenuLabel: {
            background: "transparent",
            color: "{text.color}",
          },
          submenuIcon: {
            color: "{surface.400}",
            focusColor: "{surface.500}",
            activeColor: "{surface.500}",
          },
        },
        overlay: {
          select: {
            background: "{surface.0}",
            borderColor: "{surface.300}",
            color: "{text.color}",
          },
          popover: {
            background: "{surface.100}",
            borderColor: "{surface.300}",
            color: "{text.color}",
          },
          modal: {
            background: "{surface.100}",
            borderColor: "{surface.300}",
            color: "{text.color}",
          },
        },
      },
    },
    components: componentsPreset(),
  };
};
//#region Componentes
/**
 * Ajustes por componente. Solo `colorScheme.light` (o sin `colorScheme`): PrimeVue
 * no tiene modo oscuro propio (`darkModeSelector: false`, main.ts), el claro es
 * el único que aplica y el oscuro sale de invertir las rampas; un bloque `dark`
 * aquí nunca se usaría.
 */
export const componentsPreset = (): Tokens => {
  return {
    multiselect: {
      root: {
        overlay: { background: "{surface.50}" },
      },
    },
    inputtext: {
      colorScheme: {
        "border.color": "{surface.400}",
      },
    },
    datatable: {
      header: {
        cell: {
          padding: "0 10px",
          sm: {
            padding: "0 10px",
          },
        },
      },
      // body: {
      //   cell: {
      //     padding: "0 10px",
      //     sm: {
      //       padding: "0 10px",
      //     },
      //   },
      // },
      colorScheme: {
        // body: {
        //   cell: {
        //     sm: {
        //       padding: "10px 0",
        //     },
        //   },
        // },
        row: {
          background: "{surface.0}",
        },
      },
    },

    select: {
      root: {
        overlay: { background: "{surface.50}" },
        "border.color": "{surface.400}",
      },
      overlay: { background: "{surface.0}" },
      optionGroup: {
        fontWeight: 500,
        color: "{surface.600}",
        padding: "0px 5px",
      },
    },
    popover: {
      root: {
        background: "{surface.50}",
        "border.color": "{surface.300}",
      },
    },
    divider: {
      root: {
        border: {
          color: "{surface.200}",
        },
      },
    },
    dialog: {
      root: {
        background: "{overlay.modal.background}",
      },
    },
    progressbar: {
      root: {
        background: "{surface.300}",
        height: "5px",
        label: {
          color: "{surface.0}",
        },
      },
      value: {
        background: "{surface.500}",
      },
    },
    button: {
      colorScheme: {
        light: {
          outlined: {
            secondary: {
              hoverBackground: "{surface.50}",
              activeBackground: "{surface.100}",
              borderColor: "{surface.400}",
              color: "{surface.600}",
            },
          },
        },
      },
    },
    message: {
      text: {
        font: {
          size: ".85rem",
        },
      },
      colorScheme: {
        light: {
          warn: {
            background: "{orange.500}",
            color: "{surface.0}",
          },
        },
      },
    },
    tabs: {
      colorScheme: {
        light: {
          tab: {
            activeBackground: "{surface.50}",
          },
        },
      },
    },
    accordion: {
      colorScheme: {
        light: {
          header: {
            background: "{bg}",
          },
        },
      },
    },
    tag: {
      root: {
        fontSize: "0.875rem",
        fontWeight: "600",
        padding: "0.25rem 0.5rem",
        gap: "0.25rem",
        borderRadius: "{content.border.radius}",
        roundedBorderRadius: "{border.radius.xl}",
      },
      colorScheme: {
        light: {
          success: {
            background: "{emerald.500}",
            color: "{surface.0}",
          },
        },
      },
    },
    toggleswitch: {
      root: {
        height: "10px",
      },
      colorScheme: {
        light: {
          handle: {
            background: "{surface.100}",
            checkedBackground: "{primary.100}",
          },
        },
      },
    },
    autocomplete: {
      overlay: {
        background: "{overlay.select.background}",
        borderColor: "{overlay.select.border.color}",
        borderRadius: "{overlay.select.border.radius}",
        color: "{overlay.select.color}",
        shadow: "{overlay.select.shadow}",
      },
    },
  };
};
