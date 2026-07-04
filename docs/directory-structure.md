# Estructura del Proyecto

> Generado automáticamente: `make docs-gen-dirtree`
> Raíz: `modelo`

```text
modelo/
├── .vscode/
│   └── settings.json
├── backend/
│   ├── .vscode/
│   │   ├── launch.json
│   │   └── settings.json
│   ├── assets/
│   │   ├── styles/
│   │   │   └── app.css
│   │   └── app.js
│   ├── bin/
│   │   ├── console
│   │   └── phpunit
│   ├── config/
│   │   ├── packages/
│   │   │   ├── api_platform.yaml
│   │   │   ├── asset_mapper.yaml
│   │   │   ├── cache.yaml
│   │   │   ├── csrf.yaml
│   │   │   ├── debug.yaml
│   │   │   ├── doctrine.yaml
│   │   │   ├── doctrine_migrations.yaml
│   │   │   ├── framework.yaml
│   │   │   ├── mailer.yaml
│   │   │   ├── mercure.yaml
│   │   │   ├── messenger.yaml
│   │   │   ├── monolog.yaml
│   │   │   ├── nelmio_cors.yaml
│   │   │   ├── notifier.yaml
│   │   │   ├── property_info.yaml
│   │   │   ├── routing.yaml
│   │   │   ├── security.yaml
│   │   │   ├── stof_doctrine_extensions.yaml
│   │   │   ├── translation.yaml
│   │   │   ├── twig.yaml
│   │   │   ├── validator.yaml
│   │   │   └── web_profiler.yaml
│   │   ├── routes/
│   │   │   ├── api_platform.yaml
│   │   │   ├── framework.yaml
│   │   │   ├── security.yaml
│   │   │   └── web_profiler.yaml
│   │   ├── bundles.php
│   │   ├── preload.php
│   │   ├── reference.php
│   │   ├── routes.yaml
│   │   └── services.yaml
│   ├── doc/
│   │   └── README.md
│   ├── docker/
│   │   └── php/
│   │       └── custom.ini
│   ├── frankenphp/
│   │   ├── conf.d/
│   │   │   ├── 10-app.ini
│   │   │   ├── 20-app.dev.ini
│   │   │   └── 20-app.prod.ini
│   │   ├── Caddyfile
│   │   ├── Caddyfile.dev
│   │   └── docker-entrypoint.sh
│   ├── migrations/
│   │   ├── Version20260630180058.php
│   │   ├── Version20260630180503.php
│   │   ├── Version20260630195127.php
│   │   ├── Version20260630223834.php
│   │   └── Version20260701162009.php
│   ├── public/
│   │   ├── bundles/
│   │   │   └── apiplatform/
│   │   └── index.php
│   ├── src/
│   │   ├── ApiResource/
│   │   │   ├── Agnostic.php
│   │   │   ├── ConfigVersions.php
│   │   │   ├── ConfigVersionsProvider.php
│   │   │   └── EntityConfigurationDto.php
│   │   ├── Attribute/
│   │   │   ├── ApiResourceBase.php
│   │   │   ├── ApiResourceNoPagination.php
│   │   │   └── ApiResourcePaginationPage.php
│   │   ├── Command/
│   │   │   ├── FetchIconsCommand.php
│   │   │   ├── MigracionCommand.php
│   │   │   ├── MigrarCommand.php
│   │   │   ├── MigrarEstaticosCommand.php
│   │   │   ├── MigrarIAMCommand.php
│   │   │   ├── MigrarTodoCommand.php
│   │   │   ├── ResetDbCommand.php
│   │   │   ├── SincronizarCommand.php
│   │   │   └── SyncEntityConfigurationCommand.php
│   │   ├── Controller/
│   │   │   ├── CambiarPasswordController.php
│   │   │   ├── EntityRecordCountsController.php
│   │   │   ├── ListarUsuariosController.php
│   │   │   ├── PermissionController.php
│   │   │   └── SecurityController.php
│   │   ├── DataFixtures/
│   │   │   ├── ActionFixtures.php
│   │   │   ├── PermisoFixtures.php
│   │   │   ├── RoleFixtures.php
│   │   │   ├── StatusFixtures.php
│   │   │   └── UserFixtures.php
│   │   ├── Doctrine/
│   │   │   ├── Driver/
│   │   │   └── CastFunction.php
│   │   ├── DTO/
│   │   │   ├── CollectionDTO.php
│   │   │   ├── DeleteMultipleDTO.php
│   │   │   ├── DTOBase.php
│   │   │   ├── EntityConfigurationDto.php
│   │   │   └── MetadataDTO.php
│   │   ├── Entity/
│   │   │   ├── Base/
│   │   │   ├── Configuration/
│   │   │   ├── Embeddable/
│   │   │   ├── Action.php
│   │   │   ├── ApiToken.php
│   │   │   ├── Asiento.php
│   │   │   ├── Boleto.php
│   │   │   ├── Bus.php
│   │   │   ├── BusMarca.php
│   │   │   ├── Cliente.php
│   │   │   ├── Empresa.php
│   │   │   ├── Enclave.php
│   │   │   ├── Estacion.php
│   │   │   ├── Factura.php
│   │   │   ├── Icon.php
│   │   │   ├── IconCategory.php
│   │   │   ├── Localidad.php
│   │   │   ├── Nacion.php
│   │   │   ├── Parada.php
│   │   │   ├── Permiso.php
│   │   │   ├── Piloto.php
│   │   │   ├── Recorrido.php
│   │   │   ├── RecorridoMatrioska.php
│   │   │   ├── Role.php
│   │   │   ├── Servicio.php
│   │   │   ├── Status.php
│   │   │   ├── Trayecto.php
│   │   │   ├── Usuario.php
│   │   │   └── Venta.php
│   │   ├── EntitySistemaFdn/
│   │   │   ├── Alquiler.php
│   │   │   ├── AsientoBus.php
│   │   │   ├── AutorizacionCortesia.php
│   │   │   ├── AutorizacionInterna.php
│   │   │   ├── AutorizacionOperacion.php
│   │   │   ├── Banco.php
│   │   │   ├── Boleto.php
│   │   │   ├── BoletoBitacora.php
│   │   │   ├── BoletoPaginaAsientoTemp.php
│   │   │   ├── BoletoPaginaTemp.php
│   │   │   ├── BoletosTicket.php
│   │   │   ├── Bus.php
│   │   │   ├── Caja.php
│   │   │   ├── CalendarioFacturaFecha.php
│   │   │   ├── CalendarioFacturaRuta.php
│   │   │   ├── ClaseAsiento.php
│   │   │   ├── ClaseBus.php
│   │   │   ├── Cliente.php
│   │   │   ├── Conexiones.php
│   │   │   ├── CorreoEstacion.php
│   │   │   ├── CorteVentaTalonario.php
│   │   │   ├── CorteVentaTalonarioItem.php
│   │   │   ├── CreditoAgencia.php
│   │   │   ├── CuentaBanco.php
│   │   │   ├── Departamento.php
│   │   │   ├── DepositoAgencia.php
│   │   │   ├── DiaSemana.php
│   │   │   ├── Empresa.php
│   │   │   ├── Encomienda.php
│   │   │   ├── EncomiendaBitacora.php
│   │   │   ├── EncomiendaRuta.php
│   │   │   ├── Estacion.php
│   │   │   ├── EstadoAlquiler.php
│   │   │   ├── EstadoAsiento.php
│   │   │   ├── EstadoAutorizacionOperacion.php
│   │   │   ├── EstadoBoleto.php
│   │   │   ├── EstadoBus.php
│   │   │   ├── EstadoCaja.php
│   │   │   ├── EstadoCorteVentaTalonario.php
│   │   │   ├── EstadoDeposito.php
│   │   │   ├── EstadoEncomienda.php
│   │   │   ├── EstadoReservacion.php
│   │   │   ├── EstadoSalida.php
│   │   │   ├── EstadoTarjeta.php
│   │   │   ├── Factura.php
│   │   │   ├── FacturaEmisor.php
│   │   │   ├── FacturaGenerada.php
│   │   │   ├── FechaAlquiler.php
│   │   │   ├── Galeria.php
│   │   │   ├── HorarioCiclico.php
│   │   │   ├── Imagen.php
│   │   │   ├── Impresora.php
│   │   │   ├── ImpresoraOperaciones.php
│   │   │   ├── Itinerario.php
│   │   │   ├── ItinerarioCiclico.php
│   │   │   ├── ItinerarioEspecial.php
│   │   │   ├── Job.php
│   │   │   ├── JobSync.php
│   │   │   ├── JobTag.php
│   │   │   ├── Log.php
│   │   │   ├── LogCode.php
│   │   │   ├── LogItem.php
│   │   │   ├── MarcaBus.php
│   │   │   ├── Moneda.php
│   │   │   ├── Nacionalidad.php
│   │   │   ├── Notificacion.php
│   │   │   ├── OperacionCaja.php
│   │   │   ├── Pais.php
│   │   │   ├── Piloto.php
│   │   │   ├── PluginImpresion.php
│   │   │   ├── Reservacion.php
│   │   │   ├── Rol.php
│   │   │   ├── Ruta.php
│   │   │   ├── RutaEstacionItem.php
│   │   │   ├── Salida.php
│   │   │   ├── SalidaBitacora.php
│   │   │   ├── SenalBus.php
│   │   │   ├── ServicioBus.php
│   │   │   ├── ServicioEstacion.php
│   │   │   ├── Sexo.php
│   │   │   ├── Sistema.php
│   │   │   ├── Talonario.php
│   │   │   ├── TarifaBoleto.php
│   │   │   ├── TarifaEncomienda.php
│   │   │   ├── TarifaEncomiendaDistancia.php
│   │   │   ├── TarifaEncomiendaEfectivo.php
│   │   │   ├── TarifaEncomiendaEspeciales.php
│   │   │   ├── TarifaEncomiendaPaquetesPeso.php
│   │   │   ├── TarifaEncomiendaPaquetesVolumen.php
│   │   │   ├── Tarjeta.php
│   │   │   ├── TarjetaBitacora.php
│   │   │   ├── TelefonoEstacion.php
│   │   │   ├── Tiempo.php
│   │   │   ├── TipoAutorizacionOperacion.php
│   │   │   ├── TipoBus.php
│   │   │   ├── TipoCambio.php
│   │   │   ├── TipoDocumento.php
│   │   │   ├── TipoDocumentoBoleto.php
│   │   │   ├── TipoDocumentoEncomienda.php
│   │   │   ├── TipoEncomienda.php
│   │   │   ├── TipoEncomiendaEspeciales.php
│   │   │   ├── TipoEstacion.php
│   │   │   ├── TipoOperacionCaja.php
│   │   │   ├── TipoPago.php
│   │   │   ├── TipoPagoEstacion.php
│   │   │   ├── TipoSenal.php
│   │   │   ├── TipoTarjeta.php
│   │   │   ├── TipoTipoCambio.php
│   │   │   ├── User.php
│   │   │   ├── VoucherAgencia.php
│   │   │   ├── VoucherEstacion.php
│   │   │   └── VoucherInternet.php
│   │   ├── Enum/
│   │   │   └── TipoAsiento.php
│   │   ├── Filter/
│   │   │   ├── IdPartialSearchFilter.php
│   │   │   └── OrFilter.php
│   │   ├── GraphQL/
│   │   │   └── Type/
│   │   ├── Migration/
│   │   │   ├── Limpiador.php
│   │   │   ├── Mapeador.php
│   │   │   ├── Migrador.php
│   │   │   ├── MigradorEstaticos.php
│   │   │   └── MigradorIAM.php
│   │   ├── Replication/
│   │   │   └── ReplicationService.php
│   │   ├── Repository/
│   │   │   ├── ActionRepository.php
│   │   │   ├── ApiTokenRepository.php
│   │   │   ├── BoletoRepository.php
│   │   │   ├── CustomEntityRepository.php
│   │   │   ├── EmpresaRepository.php
│   │   │   ├── EnclaveRepository.php
│   │   │   ├── EntityConfigurationRepository.php
│   │   │   ├── EstacionRepository.php
│   │   │   ├── FacturaRepository.php
│   │   │   ├── IconCategoryRepository.php
│   │   │   ├── IconRepository.php
│   │   │   ├── LocalidadRepository.php
│   │   │   ├── PermisoRepository.php
│   │   │   ├── RecorridoMatrioskaRepository.php
│   │   │   ├── RecorridoRepository.php
│   │   │   ├── RoleRepository.php
│   │   │   ├── ServicioRepository.php
│   │   │   ├── StatusRepository.php
│   │   │   ├── UsuarioRepository.php
│   │   │   └── VentaRepository.php
│   │   ├── Resolver/
│   │   │   ├── CollectionResolver.php
│   │   │   ├── UpdateEntityConfigurationFieldsResolver.php
│   │   │   └── UserByUsernameResolver.php
│   │   ├── Security/
│   │   │   ├── Voter/
│   │   │   ├── ActionExpressionProvider.php
│   │   │   ├── ApiTokenHandler.php
│   │   │   ├── GraphiQLRequestMatcher.php
│   │   │   ├── LegacySha512PasswordHasher.php
│   │   │   └── PermissionManager.php
│   │   ├── Services/
│   │   │   ├── Collection.php
│   │   │   ├── ConfigChangePublisher.php
│   │   │   ├── ConversorDivisas.php
│   │   │   ├── EntityConfigSynchronizer.php
│   │   │   ├── ServerSentEvent.php
│   │   │   └── UsuarioPasswordHasher.php
│   │   ├── Useful/
│   │   │   └── Doctrine.php
│   │   └── Kernel.php
│   ├── templates/
│   │   └── base.html.twig
│   ├── tests/
│   │   └── bootstrap.php
│   ├── translations/
│   │   └── security.es.yaml
│   ├── .dockerignore
│   ├── AGENTS.md
│   ├── compose.override.yaml
│   ├── compose.yaml
│   ├── composer.json
│   ├── Dockerfile
│   ├── importmap.php
│   ├── Makefile
│   ├── phpunit.dist.xml
│   ├── README.md
│   └── symfony.lock
├── docs/
│   ├── docs/
│   │   ├── architecture/
│   │   │   ├── c4/
│   │   │   ├── decisions/
│   │   │   └── overview.md
│   │   ├── backend/
│   │   │   ├── architecture/
│   │   │   ├── commands/
│   │   │   ├── database/
│   │   │   ├── graphql/
│   │   │   ├── iam/
│   │   │   ├── migration/
│   │   │   ├── performance/
│   │   │   ├── subdomains/
│   │   │   └── testing/
│   │   ├── docker/
│   │   │   ├── commands.md
│   │   │   ├── compose-reference.md
│   │   │   ├── environments.md
│   │   │   ├── overview.md
│   │   │   └── troubleshooting.md
│   │   ├── frontend/
│   │   │   ├── architecture/
│   │   │   ├── build-deploy/
│   │   │   ├── components/
│   │   │   ├── graphql/
│   │   │   ├── modules/
│   │   │   ├── patterns/
│   │   │   ├── stores/
│   │   │   └── styling/
│   │   ├── makefile/
│   │   │   ├── backend.md
│   │   │   ├── docker.md
│   │   │   ├── docs.md
│   │   │   ├── frontend.md
│   │   │   ├── other.md
│   │   │   └── overview.md
│   │   ├── directory-structure.md
│   │   ├── glossary.md
│   │   ├── index.md
│   │   └── technologies.md
│   ├── scripts/
│   │   ├── gen-dirtree.py
│   │   ├── gen-entity-map.py
│   │   ├── gen-erd.py
│   │   ├── gen-makefile-docs.py
│   │   ├── gen_dirtree.py
│   │   └── validate-docs.sh
│   └── mkdocs.yml
├── frontend/
│   ├── .opencode/
│   │   ├── plans/
│   │   │   └── dynamic-form-two-column.md
│   │   └── package.json
│   ├── .vscode/
│   │   ├── extensions.json
│   │   ├── launch.json
│   │   └── settings.json
│   ├── doc/
│   │   └── README.md
│   ├── public/
│   │   ├── fonts/
│   │   │   ├── DiplomataSC-Regular.ttf
│   │   │   └── FasterOne-Regular.ttf
│   │   ├── icons/
│   │   └── images/
│   │       └── logos/
│   ├── src/
│   │   ├── assets/
│   │   ├── boot/
│   │   │   ├── .gitkeep
│   │   │   ├── api-rest.ts
│   │   │   ├── apollo.ts
│   │   │   ├── formkit.ts
│   │   │   ├── gsap.ts
│   │   │   ├── i18n.ts
│   │   │   ├── introspection.ts
│   │   │   ├── middleware.ts
│   │   │   ├── responsive.ts
│   │   │   ├── server-response-listener.ts
│   │   │   ├── static-data-gateway.ts
│   │   │   └── unocss.ts
│   │   ├── components/
│   │   │   ├── admin/
│   │   │   ├── common/
│   │   │   ├── crud/
│   │   │   ├── dynamic/
│   │   │   ├── permiso/
│   │   │   ├── preload/
│   │   │   ├── role/
│   │   │   ├── sidebar/
│   │   │   ├── user/
│   │   │   ├── Breadcrumbs.vue
│   │   │   ├── ChangePasswordModal.vue
│   │   │   ├── Clock.vue
│   │   │   ├── Icon.vue
│   │   │   ├── IconPicker.vue
│   │   │   ├── Notify.vue
│   │   │   ├── ProfilerFooter.vue
│   │   │   ├── ResponsiveComponent.vue
│   │   │   ├── ResponsiveLayout.vue
│   │   │   ├── SidebarDrawer.vue
│   │   │   ├── SidebarLeft.vue
│   │   │   ├── SidebarRight.vue
│   │   │   ├── SubMenuMini.vue
│   │   │   └── Topbar.vue
│   │   ├── composables/
│   │   │   ├── breadcrumb.ts
│   │   │   ├── entityRegistry.ts
│   │   │   ├── errors.ts
│   │   │   ├── mercureItem.ts
│   │   │   ├── mercureList.ts
│   │   │   ├── notifications.ts
│   │   │   ├── useApiRest copy.ts
│   │   │   ├── useApiRest.ts
│   │   │   ├── useBreakpoints.ts
│   │   │   ├── useEntityConfig.ts
│   │   │   ├── useGsap.ts
│   │   │   ├── useIcons.ts
│   │   │   ├── useLoading.ts
│   │   │   ├── usePermission.ts
│   │   │   └── useRouter.ts
│   │   ├── config/
│   │   │   ├── breakpoints.ts
│   │   │   ├── config.ts
│   │   │   └── entityIcons.ts
│   │   ├── css/
│   │   │   ├── components/
│   │   │   ├── theme/
│   │   │   ├── variables/
│   │   │   ├── _fonts.scss
│   │   │   ├── _helpers.scss
│   │   │   ├── app.scss
│   │   │   ├── color.scss
│   │   │   ├── layout.scss
│   │   │   ├── media-queries.scss
│   │   │   ├── quasar.variables.scss
│   │   │   ├── root.scss
│   │   │   ├── tootik.scss
│   │   │   └── utopia.scss
│   │   ├── form/
│   │   │   ├── formkit-theme-fdn/
│   │   │   ├── input-schemas/
│   │   │   ├── inputs/
│   │   │   ├── plugins/
│   │   │   └── formkit.theme.ts
│   │   ├── graphql/
│   │   │   ├── links/
│   │   │   └── Request.ts
│   │   ├── i18n/
│   │   │   ├── en-US/
│   │   │   ├── index.ts
│   │   │   └── useActiveStore.ts
│   │   ├── icon-set/
│   │   │   └── custom.js
│   │   ├── layouts/
│   │   │   ├── lang/
│   │   │   ├── MainLayout.vue
│   │   │   └── MainLayout2.vue
│   │   ├── pages/
│   │   │   ├── action/
│   │   │   ├── admin/
│   │   │   ├── auth/
│   │   │   ├── error/
│   │   │   ├── permiso/
│   │   │   ├── role/
│   │   │   ├── user/
│   │   │   ├── venta/
│   │   │   ├── ErrorNotFound.vue
│   │   │   ├── IndexPage.vue
│   │   │   ├── test.html
│   │   │   └── Test.vue
│   │   ├── router/
│   │   │   ├── action.ts
│   │   │   ├── admin.ts
│   │   │   ├── boleto.ts
│   │   │   ├── index.ts
│   │   │   ├── permiso.ts
│   │   │   ├── role.ts
│   │   │   ├── routes.ts
│   │   │   ├── test.ts
│   │   │   └── user.ts
│   │   ├── services/
│   │   │   ├── api.ts
│   │   │   ├── bus.ts
│   │   │   ├── ResponsiveService.ts
│   │   │   └── StaticDataGateway.ts
│   │   ├── stores/
│   │   │   ├── action/
│   │   │   ├── autoimport/
│   │   │   ├── localidad/
│   │   │   ├── permiso/
│   │   │   ├── role/
│   │   │   ├── user/
│   │   │   ├── index.ts
│   │   │   ├── persist.ts
│   │   │   └── storeFactory.ts
│   │   ├── types/
│   │   │   ├── action.ts
│   │   │   ├── apollo.d.ts
│   │   │   ├── breadcrumb.ts
│   │   │   ├── collection.ts
│   │   │   ├── entity.ts
│   │   │   ├── error.ts
│   │   │   ├── fdn.ts
│   │   │   ├── graphql.ts
│   │   │   ├── item.ts
│   │   │   ├── permiso.ts
│   │   │   ├── role.ts
│   │   │   ├── seccion.ts
│   │   │   ├── user.ts
│   │   │   └── view.ts
│   │   ├── utils/
│   │   │   ├── autoimport/
│   │   │   ├── colors.ts
│   │   │   ├── configVersions.ts
│   │   │   ├── error.ts
│   │   │   ├── mercure.ts
│   │   │   └── unocss_rules.ts
│   │   ├── App.vue
│   │   ├── auto-imports.d.ts
│   │   ├── components.d.ts
│   │   └── env.d.ts
│   ├── .dockerignore
│   ├── .npmrc
│   ├── AGENTS.md
│   ├── Dockerfile
│   ├── formkit.config.ts
│   ├── index.html
│   ├── package.json
│   ├── postcss.config.js
│   ├── quasar.config.ts
│   ├── quasar.config.ts.temporary.compiled.1781627975923.mjs
│   ├── README.md
│   ├── tailwind.config.ts
│   ├── tsconfig.json
│   ├── uno.config.ts
│   └── uno.css
├── AGENTS.md
├── compose.override.yaml
├── compose.prod.yaml
├── compose.yaml
├── Makefile
├── opencode.js
├── opencode.json
├── opencode.json.tui-migration.bak
├── tui.json
└── update-deps.sh
```

## Directorios Principales

| Directorio | Descripción |
|------------|-------------|
| `backend/` | Symfony 8 + API Platform (API REST/GraphQL) |
| `frontend/` | Quasar + Vue 3 (SPA/PWA) |
| `docs/` | Documentación raíz (MkDocs) |
| `frontend/doc/` | Documentación técnica Frontend |
| `backend/doc/` | Documentación técnica Backend |
| `compose.yaml` | Docker Compose principal |
| `compose.override.yaml` | Override desarrollo |
| `compose.prod.yaml` | Override producción |
| `Makefile` | Comandos de orquestación |
| `AGENTS.md` | Guía para asistentes IA |

## Backend Structure

```
backend/
├── src/
│   ├── Entity/           # Entidades Doctrine (dominio)
│   ├── Repository/       # Repositorios custom
│   ├── ApiResource/      # Config API Platform
│   ├── Command/          # Comandos Messenger (CQRS)
│   ├── Controller/       # Controladores custom
│   ├── GraphQL/          # Resolvers GraphQL
│   ├── Security/         # IAM: Voters, PermissionManager
│   ├── Service/          # Servicios transversales
│   └── ...
├── config/               # Configuración Symfony
├── migrations/           # Migraciones Doctrine
├── tests/                # Tests PHPUnit
└── docker/               # Dockerfiles backend
```

## Frontend Structure

```
frontend/
├── src/
│   ├── modules/          # SUBDOMINIOS (bounded contexts)
│   │   ├── transporte/
│   │   ├── flota/
│   │   ├── venta/
│   │   ├── personal/
│   │   ├── configuracion/
│   │   ├── infraestructura/
│   │   ├── seguridad/
│   │   └── dashboard/
│   ├── components/       # Componentes globales reutilizables
│   ├── composables/      # Composables transversales
│   ├── stores/           # Pinia stores (factory pattern)
│   ├── boot/             # Secuencia de arranque (orden crítico)
│   ├── graphql/          # Documentos GraphQL + codegen
│   ├── pages/            # Vistas (file-based routing)
│   ├── layouts/          # Layouts de página
│   ├── router/           # Configuración de rutas
│   ├── services/         # Servicios transversales
│   ├── css/              # Estilos globales + tokens
│   └── utils/            # Utilidades puras
├── public/               # Assets estáticos
└── doc/                  # Documentación técnica Frontend
```
