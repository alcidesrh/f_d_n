import Aura from "@primeuix/themes/aura";
import Lara from "@primeuix/themes/lara";
import Material from "@primeuix/themes/material";
import Nora from "@primeuix/themes/nora";
import { definePreset } from "@primeuix/themes";
import type { Preset } from "@primeuix/themes/types";
import type { PrimaryColor, SurfacePalette, ThemeMode, ThemePreset } from "@/types";
import colors from "tailwindcss/colors";
import pick from "ramda/src/pick";

const RAMP_STEPS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as const;

/** Reverses every color ramp of a palette (50 ↔ 950, 100 ↔ 900, …). */
export function invertPalette<T>(palette: T): T {
  const out: Record<string, unknown> = {};
  for (const [name, value] of Object.entries(palette as object)) {
    const ramp = value as Record<string, unknown> | null;
    const isRamp = !!ramp && RAMP_STEPS.every((step) => typeof ramp[step] === "string");
    if (isRamp) {
      const reversed: Record<string, string> = {};
      RAMP_STEPS.forEach((step, i) => {
        const mirror = RAMP_STEPS[RAMP_STEPS.length - 1 - i]!;
        reversed[String(step)] = String(ramp![mirror]);
      });
      out[name] = reversed;
    } else {
      out[name] = value;
    }
  }
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

// console.log(PRESET_OPTIONS.map(v => v.value))
export const themeColors = (theme, primary, surface, dark) => {
  const option = PRESET_OPTIONS.find((o) => o.key === theme);
  let primitive = "";
  if (dark == "dark") {
    primitive = invertPalette({ ...option.value.primitive, ...colors });
    primary = invertPalette(pick([primary], colors))[primary];
    surface = invertPalette(pick([surface], colors))[surface];
    surface = { "0": "#000000", ...surface };
  } else {
    primitive = { ...option.value.primitive, ...colors };
    primary = colors[primary];
    surface = { "0": "#ffffff", ...colors[surface] };
  }
  return {
    ...option.value,
    primitive: primitive,
    semantic: {
      ...option.value.semantic,
      transitionDuration: ".2s",
      primary: primary,
      colorScheme: {
        ...option.value.semantic?.colorScheme?.light,
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
          background: "{surface.0}",
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
            background: "{surface.100}",
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
    components: { ...option.value.components, ...componentsPreset(theme) },
  };

  return {
    tailwindPalete: colors,
    primitive: { ...option.value.primitive, ...colors },
    primary: primary,
  };
};
//#region Componentes
export const componentsPreset = (parent: string) => {
  const option = PRESET_OPTIONS.find((o) => o.key === parent);
  return {
    multiselect: {
      ...option.value.components.multiselect,
      colorScheme: {
        ...option.value.components.multiselect?.colorScheme,
        dark: {
          ...option.value.components.multiselect?.colorScheme?.dark,
          background: "{surface.50}",
          "border.color": "{surface.400}",
        },
      },
    },
    inputtext: {
      ...option.value.components.inputtext,
      colorScheme: {
        ...option.value.components.inputtext?.colorScheme,
        dark: {
          ...option.value.components.inputtext?.colorScheme?.dark,
          background: "{surface.50}",
          "border.color": "{surface.400}",
        },
      },
    },
    datatable: {
      ...option.value.components.datatable,
      header: {
        cell: {
          padding: "5px 1rem",
        },
      },
      colorScheme: {
        ...option.value.components.datatable?.colorScheme,
        dark: {
          ...option.value.components.datatable?.colorScheme.dark,
          // header: {
          //   background: "{surface.100}",
          //   "cell.background": "{surface.100}",
          // },
          row: {
            background: "{surface.100}",
            "striped.background": "{surface.200}",
          },
        },
      },
    },
    select: {
      ...option.value.components.select,
      root: {
        ...option.value.components.select?.root,
        // background: "{surface.50}",
        // "border.color": "{surface.400}",
      },
    },
    popover: {
      ...option.value.components.popover,
      root: {
        ...option.value.components.popover?.root,
        background: "{surface.50}",
        "border.color": "{surface.300}",
      },
      // colorScheme: {
      // ...option.value.components.popover?.colorScheme,
      //   dark: {
      //     ...option.value.components.popover.dark,
      //     background: "{surface.100}",
      //   },
      // },
    },
    divider: {
      ...option.value.components.divider,
      root: {
        ...option.value.components.divider?.root,
        border: {
          color: "{surface.200}",
        },
      },
    },
    dialog: {
      ...option.value.components.dialog,
      root: {
        ...option.value.components.dialog?.root,
        background: "{overlay.modal.background}",
      },
    },

    // },
  };
};
//#endregion
