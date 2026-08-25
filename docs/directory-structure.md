# Estructura del Proyecto

> Generado automáticamente: `make docs-gen-dirtree`
> Raíz: `f_d_n`

```text
f_d_n/
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
│   │   ├── architecture/
│   │   │   ├── directories.md
│   │   │   └── overview.md
│   │   ├── database/
│   │   │   ├── erd.md
│   │   │   └── overview.md
│   │   ├── iam/
│   │   │   └── overview.md
│   │   ├── migration/
│   │   │   └── overview.md
│   │   ├── subdomains/
│   │   │   └── overview.md
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
│   ├── public/
│   │   ├── bundles/
│   │   │   └── apiplatform/
│   │   └── index.php
│   ├── src/
│   │   ├── ApiResource/
│   │   │   ├── Agnostic.php
│   │   │   ├── ConfigVersions.php
│   │   │   ├── ConfigVersionsProvider.php
│   │   │   ├── EntityConfigurationByEntityClassProvider.php
│   │   │   └── EntityConfigurationDto.php
│   │   ├── Attribute/
│   │   │   ├── ApiResourceBase.php
│   │   │   ├── ApiResourceNoPagination.php
│   │   │   └── ApiResourcePaginationPage.php
│   │   ├── Command/
│   │   │   ├── DebugGraphQlOpsCommand.php
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
│   │   │   ├── SecurityController.php
│   │   │   └── SyncVueRoutesController.php
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
│   │   │   ├── Embeddable/
│   │   │   ├── Enum/
│   │   │   ├── Action.php
│   │   │   ├── ApiToken.php
│   │   │   ├── Asiento.php
│   │   │   ├── Boleto.php
│   │   │   ├── Bus.php
│   │   │   ├── BusMarca.php
│   │   │   ├── Cliente.php
│   │   │   ├── CollectionFieldConfig.php
│   │   │   ├── Empresa.php
│   │   │   ├── Enclave.php
│   │   │   ├── EntityConfiguration.php
│   │   │   ├── Estacion.php
│   │   │   ├── Factura.php
│   │   │   ├── FieldConfig.php
│   │   │   ├── FormFieldConfig.php
│   │   │   ├── Icon.php
│   │   │   ├── LayoutProfile.php
│   │   │   ├── LayoutProfileRole.php
│   │   │   ├── LayoutProfileUsuario.php
│   │   │   ├── LayoutSchema.php
│   │   │   ├── LayoutSchemaItem.php
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
│   │   │   ├── Venta.php
│   │   │   └── VueRoute.php
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
│   │   ├── Error/
│   │   │   └── ErrorHandler.php
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
│   │   │   ├── IconRepository.php
│   │   │   ├── LayoutSchemaRepository.php
│   │   │   ├── LocalidadRepository.php
│   │   │   ├── PermisoRepository.php
│   │   │   ├── RecorridoMatrioskaRepository.php
│   │   │   ├── RecorridoRepository.php
│   │   │   ├── RoleRepository.php
│   │   │   ├── ServicioRepository.php
│   │   │   ├── StatusRepository.php
│   │   │   ├── UsuarioRepository.php
│   │   │   ├── VentaRepository.php
│   │   │   └── VueRouteRepository.php
│   │   ├── Resolver/
│   │   │   ├── CollectionResolver.php
│   │   │   ├── DeleteMultipleMutationResolver.php
│   │   │   ├── EntityConfigurationRefresh.php
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
│   │   │   ├── UsuarioPasswordHasher.php
│   │   │   └── VueRouteSynchronizer.php
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
│   ├── architecture/
│   │   ├── c4/
│   │   │   └── index.md
│   │   ├── decisions/
│   │   │   ├── ADR-001-api-platform-graphql.md
│   │   │   ├── ADR-002-dual-entity-manager.md
│   │   │   ├── ADR-003-flat-permission-set.md
│   │   │   ├── ADR-004-dynamic-crud-store-factory.md
│   │   │   ├── ADR-005-frankenphp-mercure.md
│   │   │   ├── ADR-006-subdomains-bounded-contexts.md
│   │   │   ├── ADR-007-formkit-custom-theme.md
│   │   │   ├── ADR-008-legacy-migration-strategy.md
│   │   │   ├── ADR-009-autoimport-system.md
│   │   │   ├── ADR-010-dynamic-entity-config.md
│   │   │   └── index.md
│   │   └── overview.md
│   ├── backend/
│   │   ├── architecture/
│   │   │   ├── api-platform.md
│   │   │   ├── doctrine-dual-em.md
│   │   │   ├── mercure.md
│   │   │   ├── messenger.md
│   │   │   └── overview.md
│   │   ├── commands/
│   │   │   ├── fixtures.md
│   │   │   ├── migration.md
│   │   │   ├── overview.md
│   │   │   └── sync.md
│   │   ├── database/
│   │   │   ├── entity-map.md
│   │   │   ├── entity-map.mmd
│   │   │   ├── indexing.md
│   │   │   ├── legacy-sqlserver.md
│   │   │   ├── migrations.md
│   │   │   ├── modeling-analysis.md
│   │   │   └── overview.md
│   │   ├── graphql/
│   │   │   ├── nplus1.md
│   │   │   ├── overview.md
│   │   │   ├── resolvers.md
│   │   │   └── schema.md
│   │   ├── iam/
│   │   │   ├── api-tokens.md
│   │   │   ├── overview.md
│   │   │   ├── permission-manager.md
│   │   │   ├── permission-matrix.md
│   │   │   └── voters.md
│   │   ├── migration/
│   │   │   ├── config.md
│   │   │   ├── iam.md
│   │   │   ├── overview.md
│   │   │   ├── pipeline.md
│   │   │   ├── static-entities.md
│   │   │   └── tickets.md
│   │   ├── performance/
│   │   │   ├── caching.md
│   │   │   ├── overview.md
│   │   │   ├── profiling.md
│   │   │   └── queries.md
│   │   ├── subdomains/
│   │   │   ├── configuracion/
│   │   │   ├── flota/
│   │   │   ├── general/
│   │   │   ├── infraestructura/
│   │   │   ├── personal/
│   │   │   ├── seguridad/
│   │   │   ├── transporte/
│   │   │   ├── venta/
│   │   │   └── overview.md
│   │   └── testing/
│   │       ├── fixtures.md
│   │       ├── integration.md
│   │       ├── overview.md
│   │       └── unit.md
│   ├── docker/
│   │   ├── commands.md
│   │   ├── compose-reference.md
│   │   ├── environments.md
│   │   ├── overview.md
│   │   └── troubleshooting.md
│   ├── frontend/
│   │   ├── architecture/
│   │   │   ├── boot-sequence.md
│   │   │   ├── data-layer.md
│   │   │   ├── dynamic-crud.md
│   │   │   ├── overview.md
│   │   │   └── routing.md
│   │   ├── build-deploy/
│   │   │   ├── docker.md
│   │   │   ├── overview.md
│   │   │   ├── production.md
│   │   │   └── vite.md
│   │   ├── components/
│   │   │   ├── admin.md
│   │   │   ├── auth.md
│   │   │   ├── crud-collection.md
│   │   │   ├── crud-form.md
│   │   │   ├── dynamic.md
│   │   │   ├── overview.md
│   │   │   └── ui-commons.md
│   │   ├── graphql/
│   │   │   ├── apollo-config.md
│   │   │   ├── codegen.md
│   │   │   ├── links.md
│   │   │   └── overview.md
│   │   ├── modules/
│   │   │   ├── configuracion.md
│   │   │   ├── dashboard.md
│   │   │   ├── flota.md
│   │   │   ├── infraestructura.md
│   │   │   ├── overview.md
│   │   │   ├── personal.md
│   │   │   ├── seguridad.md
│   │   │   ├── transporte.md
│   │   │   └── venta.md
│   │   ├── patterns/
│   │   │   ├── composables.md
│   │   │   ├── dynamic-crud.md
│   │   │   ├── formkit-metadata.md
│   │   │   ├── i18n.md
│   │   │   ├── overview.md
│   │   │   └── unocss.md
│   │   ├── stores/
│   │   │   ├── autoimport.md
│   │   │   ├── overview.md
│   │   │   ├── schema.md
│   │   │   ├── session.md
│   │   │   └── store-factory.md
│   │   └── styling/
│   │       ├── colors.md
│   │       ├── custom-rules.md
│   │       ├── overview.md
│   │       └── unocss-config.md
│   ├── makefile/
│   │   ├── backend.md
│   │   ├── docker.md
│   │   ├── docs.md
│   │   ├── frontend.md
│   │   ├── other.md
│   │   └── overview.md
│   ├── directory-structure.md
│   ├── glossary.md
│   ├── index.md
│   └── technologies.md
├── frontend/
│   ├── .vscode/
│   │   ├── extensions.json
│   │   ├── launch.json
│   │   └── settings.json
│   ├── agents/
│   │   ├── formularios.md
│   │   ├── layout.md
│   │   └── Promt-refactorizacion.md
│   ├── e2e/
│   │   ├── tsconfig.json
│   │   └── vue.spec.ts
│   ├── public/
│   │   ├── fonts/
│   │   │   ├── DiplomataSC-Regular.ttf
│   │   │   └── FasterOne-Regular.ttf
│   │   ├── images/
│   │   │   ├── logo/
│   │   │   └── logos/
│   │   └── schema.graphql
│   ├── src/
│   │   ├── assets/
│   │   │   ├── animations.css
│   │   │   ├── content.css
│   │   │   ├── fonts.css
│   │   │   ├── header.css
│   │   │   ├── main.css
│   │   │   ├── sidebar.css
│   │   │   ├── theme-editor.css
│   │   │   └── tokens.css
│   │   ├── components/
│   │   │   ├── common/
│   │   │   ├── crud/
│   │   │   ├── formbuilder/
│   │   │   ├── formkit/
│   │   │   ├── icons/
│   │   │   ├── layout/
│   │   │   └── Toasts.vue
│   │   ├── composables/
│   │   │   ├── __tests__/
│   │   │   ├── useEntityForm.ts
│   │   │   ├── useEntityRegistry.ts
│   │   │   ├── useLayout.ts
│   │   │   └── useToasts.ts
│   │   ├── config/
│   │   │   ├── nav.ts
│   │   │   └── theme.ts
│   │   ├── data/
│   │   │   └── mock.ts
│   │   ├── layouts/
│   │   │   ├── auth.vue
│   │   │   ├── auth2.vue
│   │   │   ├── blank.vue
│   │   │   ├── default.vue
│   │   │   └── formdemo.vue
│   │   ├── lib/
│   │   │   └── apollo/
│   │   ├── pages/
│   │   │   ├── auth/
│   │   │   ├── errors/
│   │   │   ├── form/
│   │   │   └── Dashboard.vue
│   │   ├── router/
│   │   │   └── index.ts
│   │   ├── stores/
│   │   │   ├── __tests__/
│   │   │   ├── entities/
│   │   │   ├── formBuilder.ts
│   │   │   ├── global.ts
│   │   │   ├── pinia.ts
│   │   │   ├── schemaRepository.ts
│   │   │   └── ui.ts
│   │   ├── types/
│   │   │   ├── entities/
│   │   │   ├── formkit-inputs.d.ts
│   │   │   ├── index.ts
│   │   │   └── vue-router.d.ts
│   │   ├── utils/
│   │   │   └── formkit/
│   │   ├── App.vue
│   │   ├── auto-imports.d.ts
│   │   ├── components.d.ts
│   │   ├── formkit.config.ts
│   │   └── main.ts
│   ├── .gitattributes
│   ├── .oxfmtrc.json
│   ├── .oxlintrc.json
│   ├── AGENTS.md
│   ├── bun.lock
│   ├── Dockerfile
│   ├── env.d.ts
│   ├── eslint.config.ts
│   ├── index.html
│   ├── package.json
│   ├── playwright.config.ts
│   ├── README.md
│   ├── tsconfig.app.json
│   ├── tsconfig.json
│   ├── tsconfig.node.json
│   ├── tsconfig.vitest.json
│   ├── uno.config.ts
│   ├── vite.config.ts
│   └── vitest.config.ts
├── monitoring/
│   ├── grafana/
│   │   ├── dashboards/
│   │   │   ├── dashboard.yml
│   │   │   └── docker-containers.json
│   │   └── datasources/
│   │       └── datasource.yml
│   └── prometheus/
│       └── prometheus.yml
├── scripts/
│   ├── gen-dirtree.py
│   ├── gen-entity-map.py
│   ├── gen-erd.py
│   ├── gen-makefile-docs.py
│   ├── gen_dirtree.py
│   └── validate-docs.sh
├── .workshop.lock
├── AGENTS.md
├── borrar.json
├── compose.monitoring.yaml
├── compose.override.yaml
├── compose.prod.yaml
├── compose.yaml
├── kitty
├── Makefile
├── mkdocs.yml
├── opencode.js
├── opencode.json
├── opencode.json.tui-migration.bak
├── tui.json
├── update-deps.sh
└── workshop.yaml
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
