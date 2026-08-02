import {
  defineConfig,
  presetAttributify,
  presetWind4,
  transformerDirectives,
  transformerVariantGroup,
} from "unocss";
import type { Rule } from "unocss";
// import { colors } from "./src/utils/colors";
// import { color_rules, utopia_rules } from "./src/utils/unocss_rules";

const SHADE_KEYS = [
  "50",
  "100",
  "200",
  "300",
  "400",
  "500",
  "600",
  "700",
  "800",
  "900",
  "950",
] as const;

export default defineConfig({
  // rules: [...(utopia_rules as Rule<object>[]), ...(color_rules as Rule<object>[])],
  presets: [
    presetAttributify(),
    presetWind4({
      preflights: {
        reset: true,
        theme: true,
      },
    }),
  ],
  // theme: {
  //   colors: {
  //     ...colors,
  //   },
  // },
  // preflights: [
  //   {
  //     getCSS: () => {
  //       const vars = Object.entries(colors.surface)
  //         .map(([k, v]) => `--colors-surface-${k}: ${v};`)
  //         .join("\n");

  //       return `
  //         :root {
  //           ${vars}
  //         }
  //       `;
  //     },
  //   },
  // ],
  preflights: [
    {
      getCSS: ({ theme }) => {
        const { colors } = theme;
        const darkRules = Object.entries(colors).flatMap(([name, shades]) => {
          if (typeof shades !== "object") return [];
          const numeric = SHADE_KEYS.map((shade, index) => {
            const mirrored = SHADE_KEYS[SHADE_KEYS.length - 1 - index];
            return `--colors-${name}-${shade}: ${shades[mirrored]};`;
          });
          return [...numeric, `--colors-${name}-DEFAULT: ${shades["600"]};`];
        });
        return `html.dark {\n  ${darkRules.join("\n  ")}\n}`;
      },
    },
  ],
  // theme
  // extendTheme: (theme) => {
  // 	const result = {
  // 		...theme,
  // 		colors: {
  // 			...theme.colors,
  // 			...colors,
  // 		},
  // 	}
  // 	return result
  // },
  // layers: {
  //   reset: -10,
  //   quasar: -5,
  //   theme: -3,
  //   base: 10,
  //   components: 20,
  //   overrides: 30,
  // },
  transformers: [transformerDirectives(), transformerVariantGroup()],
  content: {
    pipeline: {
      include: [
        // "src/form/formkit.theme.ts",
        // "src/form/inputs/**/*.{ts,js,vue}",
        /\.(vue|svelte|[jt]sx|mdx?|astro|elm|php|phtml|html)($|\?)/,
        // "src/utils/**/*.{ts,js,vue}",
        // "src/composables/**/*.{ts,js,vue}",
        // "src/stores/**/*.{ts,js,vue}",
        // "src/pages/**/*.{ts,js,vue}",
        // "src/layouts/**/*.{ts,js,vue}",
        "src/components/**/*.{ts,js,vue}",
      ],
      // exclude files
      // exclude: []
    },
    //   // filesystem: [
    //   //   // 'layers/**/*.{js,ts.vue}',
    //   //   // 'layers/auth/components/form/*.{ts,js,vue}',
    //   //   // 'composables/**/*.{ts,js,vue}',
    //   //   // './pages/**/*.{ts,js,vue}',
    //   //   // './node_modules/@primevue/**/*.{vue,js,ts,jsx,tsx}'
    //   // ],
  },
});
