// Configuration for your app
// https://v2.quasar.dev/quasar-cli-vite/quasar-config-file

import { defineConfig } from '#q-app/wrappers'
import { fileURLToPath } from 'node:url'
import path from 'path'
import UnoCSS from 'unocss/vite'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

export default defineConfig((ctx) => {
	return {
		boot: [
			// 'pinia',
			'unocss',
			'api-rest',
			'apollo',
			'server-response-listener',
			'formkit',
			'introspection',
			'middleware',
			'static-data-gateway',
			'i18n',
			'responsive',
			'gsap',
		],

		// https://v2.quasar.dev/quasar-cli-vite/quasar-config-file#css
		css: ['app.scss'],

		// https://github.com/quasarframework/quasar/tree/dev/extras
		extras: [
			// 'ionicons-v4',
			// 'mdi-v7',
			// 'fontawesome-v6',
			// 'eva-icons',
			// 'themify',
			// 'line-awesome',
			// 'roboto-font-latin-ext', // this or either 'roboto-font', NEVER both!
			// "roboto-font", // optional, you are not bound to it
			// "material-icons", // optional, you are not bound to it
			// "material-symbols-outlined",
		],

		// Full list of options: https://v2.quasar.dev/quasar-cli-vite/quasar-config-file#build
		build: {
			// alias: {
			//   "quasar/dist/quasar.sass": path.resolve(
			//     import.meta.dirname,
			//     "./src/css/app.scss",
			//   ),
			// "src/css/quasar.variables.scss": path.resolve(
			//   import.meta.dirname,
			//   "src/css/quasar.variables.sass",
			// ),
			// },
			target: {
				browser: ['es2022', 'firefox115', 'chrome115', 'safari14'],
				node: 'node20',
			},

			typescript: {
				strict: true,
				vueShim: true,
				// extendTsConfig (tsConfig) {}
			},

			vueRouterMode: 'history', // available values: 'hash', 'history'
			// vueRouterBase,
			// vueDevtools,
			// vueOptionsAPI: false,

			// rebuildCache: true, // rebuilds Vite/linter/etc cache on startup

			// publicPath: '/',
			// analyze: true,
			// env: {},
			// rawDefine: {}
			// ignorePublicFolder: true,
			// minify: false,
			// polyfillModulePreload: true,
			// distDir

			// extendViteConf (viteConf) {},
			// viteVuePluginOptions: {},
			vitePlugins: [
				// {
				//   name: "quasar-strip-sass",
				//   enforce: "pre",
				//   transform(code, id) {
				//     if (code.includes(`import 'quasar/dist/quasar.sass'`)) {
				//       code = code.replaceAll(
				//         "import 'quasar/dist/quasar.sass'",
				//         "import 'virtual:uno.css'",
				//       );
				//     }
				//     return code;
				//   },
				// },
				// [
				//   "@intlify/unplugin-vue-i18n/vite",
				//   {
				//     // if you want to use Vue I18n Legacy API, you need to set `compositionOnly: false`
				//     // compositionOnly: false,

				//     // if you want to use named tokens in your Vue I18n messages, such as 'Hello {name}',
				//     // you need to set `runtimeOnly: false`
				//     // runtimeOnly: false,

				//     ssr: ctx.modeName === "ssr",

				//     // you need to set i18n resource including paths !
				//     include: [fileURLToPath(new URL("./src/i18n", import.meta.url))],
				//   },
				// ],
				Components({
					dirs: ['src/components'], // 👈 agrega tu carpeta aquí
					extensions: ['vue'],
					deep: true,
					dts: 'src/components.d.ts',
				}),
				AutoImport({
					// rutas para autoimportar tus composables
					dirs: [
						'src/composables',
						'src/stores/autoimport/**/*',
						'src/utils/autoimport/**/*',
						'src/config',
						'src/graphql',
						'src/services',

						// // Auto imports dentro de módulos feature
						// 'src/modules/*/composables',
						// 'src/modules/*/stores',
						// 'src/modules/*/utils'
					],

					// autoimportar APIs de Vue y Quasar
					imports: [
						'vue',
						'vue-router',
						'pinia',
						{
							quasar: ['useQuasar'],
						},
					],

					// generar archivo de tipos (opcional)
					dts: 'src/auto-imports.d.ts',
					vueTemplate: true,
				}) as Plugin,
				//Tu use Layers
				// [VueDevTools(), {}],
			],
			extendViteConf(viteConf: Record<any, any>) {
				// ----------------------------------------
				// ALIAS @ y ~
				// ----------------------------------------
				viteConf.resolve.alias = {
					...(viteConf.resolve.alias || {}),
					'@': path.resolve(__dirname, './src'),
					// "~": path.resolve(__dirname, "./src"),
				}

				viteConf.plugins.push(UnoCSS())
				// if (process.env.NODE_ENV === "development") {
				//   viteConf.plugins.push(
				//     VueDevTools({
				//       // opciones opcionales
				//       appendTo: "body",
				//       componentInspector: true,
				//     }),
				//   );
				// }

				viteConf.css = viteConf.css || {}
				viteConf.css.postcss = viteConf.css.postcss || {}
				viteConf.css.postcss.plugins = viteConf.css.postcss.plugins || []

				viteConf.css = viteConf.css || {}
				viteConf.css.preprocessorOptions = viteConf.css.preprocessorOptions || {}
				viteConf.css.preprocessorOptions.scss = viteConf.css.preprocessorOptions.scss || {}
				viteConf.css.preprocessorOptions.scss.additionalData = `
        @function -alpha($color, $alpha) {
          @if unit($alpha) == '%' {
            $alpha: $alpha / 100%;
          }
          $color-str: inspect($color); // Convert to string
          $modified: str-replace($color-str, ')', ' / ' + $alpha + ')');
          @return unquote($modified);
        }
        @function str-replace($string, $search, $replace: '') {
          $index: str-index($string, $search);
          @if $index {
            @return str-slice($string, 1, $index - 1) + $replace + str-replace(str-slice($string, $index + str-length($search)), $search, $replace);
          }
          @return $string;
        }
        `
			},
		},

		// Full list of options: https://v2.quasar.dev/quasar-cli-vite/quasar-config-file#devserver
		devServer: {
			// https: true,
			open: true, // opens browser window automatically
		},

		// https://v2.quasar.dev/quasar-cli-vite/quasar-config-file#framework
		framework: {
			iconSet: 'material-symbols-outlined', // Quasar icon set
			lang: 'en-US', // Quasar language pack

			// For special cases outside of where the auto-import strategy can have an impact
			// (like functional components as one of the examples),
			// you can manually specify Quasar components/directives to be available everywhere:
			//
			// components: [],
			// directives: [],

			// Quasar plugins
			plugins: ['Notify', 'Dialog', 'LoadingBar'],
			config: {
				screen: {
					bodyClasses: true, // <<< add this
				},
				notify: {
					position: 'top',
					multiLine: true,
					timeout: 4000,
					classes: 'fdn-notify',
				},
			},
		},

		animations: 'all', // --- includes all animations
		// https://v2.quasar.dev/options/animations
		// animations: [],

		// https://v2.quasar.dev/quasar-cli-vite/quasar-config-file#sourcefiles
		// sourceFiles: {
		//   rootComponent: 'src/App.vue',
		//   router: 'src/router/index',
		//   store: 'src/store/index',
		//   pwaRegisterServiceWorker: 'src-pwa/register-service-worker',
		//   pwaServiceWorker: 'src-pwa/custom-service-worker',
		//   pwaManifestFile: 'src-pwa/manifest.json',
		//   electronMain: 'src-electron/electron-main',
		//   electronPreload: 'src-electron/electron-preload'
		//   bexManifestFile: 'src-bex/manifest.json
		// },

		// https://v2.quasar.dev/quasar-cli-vite/developing-ssr/configuring-ssr
		ssr: {
			prodPort: 3000, // The default port that the production server should use
			// (gets superseded if process.env.PORT is specified at runtime)

			middlewares: [
				'render', // keep this as last one
			],

			// extendPackageJson (json) {},
			// extendSSRWebserverConf (esbuildConf) {},

			// manualStoreSerialization: true,
			// manualStoreSsrContextInjection: true,
			// manualStoreHydration: true,
			// manualPostHydrationTrigger: true,

			pwa: false,
			// pwaOfflineHtmlFilename: 'offline.html', // do NOT use index.html as name!

			// pwaExtendGenerateSWOptions (cfg) {},
			// pwaExtendInjectManifestOptions (cfg) {}
		},

		// https://v2.quasar.dev/quasar-cli-vite/developing-pwa/configuring-pwa
		pwa: {
			workboxMode: 'GenerateSW', // 'GenerateSW' or 'InjectManifest'
			// swFilename: 'sw.js',
			// manifestFilename: 'manifest.json',
			// extendManifestJson (json) {},
			// useCredentialsForManifestTag: true,
			// injectPwaMetaTags: false,
			// extendPWACustomSWConf (esbuildConf) {},
			// extendGenerateSWOptions (cfg) {},
			// extendInjectManifestOptions (cfg) {}
		},

		// Full list of options: https://v2.quasar.dev/quasar-cli-vite/developing-cordova-apps/configuring-cordova
		cordova: {
			// noIosLegacyBuildFlag: true, // uncomment only if you know what you are doing
		},

		// Full list of options: https://v2.quasar.dev/quasar-cli-vite/developing-capacitor-apps/configuring-capacitor
		capacitor: {
			hideSplashscreen: true,
		},

		// Full list of options: https://v2.quasar.dev/quasar-cli-vite/developing-electron-apps/configuring-electron
		electron: {
			// extendElectronMainConf (esbuildConf) {},
			// extendElectronPreloadConf (esbuildConf) {},

			// extendPackageJson (json) {},

			// Electron preload scripts (if any) from /src-electron, WITHOUT file extension
			preloadScripts: ['electron-preload'],

			// specify the debugging port to use for the Electron app when running in development mode
			inspectPort: 5858,

			bundler: 'packager', // 'packager' or 'builder'

			packager: {
				// https://github.com/electron-userland/electron-packager/blob/master/docs/api.md#options
				// OS X / Mac App Store
				// appBundleId: '',
				// appCategoryType: '',
				// osxSign: '',
				// protocol: 'myapp://path',
				// Windows only
				// win32metadata: { ... }
			},

			builder: {
				// https://www.electron.build/configuration/configuration

				appId: 'frontend',
			},
		},

		// Full list of options: https://v2.quasar.dev/quasar-cli-vite/developing-browser-extensions/configuring-bex
		bex: {
			// extendBexScriptsConf (esbuildConf) {},
			// extendBexManifestJson (json) {},

			/**
			 * The list of extra scripts (js/ts) not in your bex manifest that you want to
			 * compile and use in your browser extension. Maybe dynamic use them?
			 *
			 * Each entry in the list should be a relative filename to /src-bex/
			 *
			 * @example [ 'my-script.ts', 'sub-folder/my-other-script.js' ]
			 */
			extraScripts: [],
		},
	}
})
