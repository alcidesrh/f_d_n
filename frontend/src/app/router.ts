import { createRouter, createWebHistory } from "vue-router";
import { loginHacia } from "@/core/auth/redirect";
import { useSessionStore } from "@/core/auth/session";
import { useNavigationHistoryStore } from "@/app/layout/navigationHistory";
import { destinoDeRuta, useVentanaChat } from "@/features/chat/ventana";

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior() {
    return { top: 0 };
  },
  routes: [
    {
      path: "/",
      name: "dashboard",
      component: () => import("@/features/dashboard/DashboardPage.vue"),
      meta: {
        title: "Tablero de gestión",
      },
    },
    {
      path: "/login",
      name: "login",
      component: () => import("@/features/auth/LoginPage.vue"),
      meta: {
        layout: "blank",
        title: "Iniciar Sesión",
        public: true,
      },
    },
    {
      path: "/mi-cuenta",
      name: "mi-cuenta",
      component: () => import("@/features/cuenta/MiCuentaPage.vue"),
      meta: {
        title: "Mi cuenta",
      },
    },
    {
      path: "/form/build",
      name: "form_build",
      component: () => import("@/features/form-builder/FormBuilderPage.vue"),
      meta: {
        title: "Constructor de formularios",
        panel: {
          component: () => import("@/features/form-builder/FormBuilderPanel.vue"),
          width: 350,
        },
      },
    },
    {
      path: "/migracion",
      name: "migracion",
      component: () => import("@/features/migracion/MigracionPage.vue"),
      meta: {
        title: "Migración legado → nuevo",
        panel: { component: () => import("@/features/migracion/MigracionPanel.vue"), width: 340 },
      },
    },
    {
      path: "/configuracion/entidades",
      name: "entity-config",
      component: () => import("@/features/entity-config/EntityConfigPage.vue"),
      meta: {
        title: "Configuración de entidades",
        label: "Config. entidades",
        icon: "tune",
      },
    },
    {
      path: "/configuracion/menus",
      name: "menu-builder",
      component: () => import("@/features/menu-builder/MenuBuilderPage.vue"),
      meta: {
        title: "Menús de navegación",
        label: "Menús",
        icon: "account-tree-outline",
      },
    },
    {
      path: "/venta",
      name: "venta",
      component: () => import("@/features/venta/VentaPage.vue"),
      meta: {
        title: "Venta de boletos",
        label: "Emitir Boleto",
        icon: "confirmation-number-outline",
      },
    },
    {
      path: "/boletos",
      name: "boletos",
      component: () => import("@/features/boleto/BoletosPage.vue"),
      meta: {
        title: "Boletos",
        label: "Boletos",
        icon: "confirmation-number-outline",
      },
    },
    {
      path: "/encomiendas",
      name: "encomiendas",
      component: () => import("@/features/encomienda/EncomiendasPage.vue"),
      meta: {
        title: "Encomiendas",
        label: "Encomiendas",
        icon: "inventory-2-outline",
      },
    },
    {
      path: "/encomiendas/registrar",
      name: "encomienda-registrar",
      component: () => import("@/features/encomienda/RegistrarEncomiendaPage.vue"),
      meta: {
        title: "Registrar encomienda",
        label: "Registrar encomienda",
        icon: "package-2-outline",
      },
    },
    {
      path: "/encomiendas/salida",
      name: "encomienda-salida",
      component: () => import("@/features/encomienda/ProcesarSalidaPage.vue"),
      meta: {
        title: "Encomiendas por salida",
        label: "Procesar por salida",
        icon: "local-shipping-outline",
      },
    },
    {
      path: "/encomiendas/entregar",
      name: "encomienda-entregar",
      component: () => import("@/features/encomienda/EntregarEncomiendasPage.vue"),
      meta: {
        title: "Entregar encomiendas",
        label: "Entregar",
        icon: "handshake-outline",
      },
    },
    {
      path: "/encomiendas/pendientes-envio",
      name: "encomienda-pendientes-envio",
      component: () => import("@/features/encomienda/PendientesEnvioPage.vue"),
      meta: {
        title: "Encomiendas pendientes de envío",
        label: "Pendientes de envío",
        icon: "pending-actions",
      },
    },
    {
      path: "/encomiendas/especiales",
      name: "encomienda-especiales",
      component: () => import("@/features/encomienda/EspecialesPage.vue"),
      meta: {
        title: "Encomiendas especiales",
        label: "Especiales",
        icon: "category-outline",
      },
    },
    {
      path: "/salidas",
      name: "salidas",
      component: () => import("@/features/salida/SalidasPage.vue"),
      meta: {
        title: "Salidas",
        label: "Salidas",
        icon: "directions-bus-outline",
      },
    },
    {
      path: "/rutas",
      name: "rutas",
      component: () => import("@/features/ruta/RutasPage.vue"),
      meta: {
        title: "Rutas",
        label: "Rutas",
        icon: "route",
      },
    },
    {
      path: "/rutas/:id(nueva|\\d+)",
      name: "ruta",
      component: () => import("@/features/ruta/RutaEditorPage.vue"),
      meta: {
        title: "Ruta",
        label: "Ruta",
        icon: "route",
      },
    },
    {
      path: "/tarifas/mapa",
      name: "tarifas-mapa",
      component: () => import("@/features/tarifa/TarifasMapaPage.vue"),
      meta: {
        title: "Tarifas en el mapa",
        label: "Tarifas en el mapa",
        icon: "map-outline",
      },
    },
    {
      path: "/seguimiento",
      name: "seguimiento",
      component: () => import("@/features/seguimiento/SeguimientoPage.vue"),
      meta: {
        title: "Buses en recorrido",
        label: "Seguimiento",
        icon: "location-on-outline",
      },
    },
    {
      path: "/salidas/programar",
      name: "salidas-programar",
      component: () => import("@/features/salida/ProgramadorPage.vue"),
      meta: {
        title: "Programar salidas",
        label: "Programar salidas",
        icon: "calendar-add-on-outline",
      },
    },
    {
      path: "/salidas/chequeo",
      name: "salidas-chequeo",
      component: () => import("@/features/salida/ChequeoPage.vue"),
      meta: {
        title: "Chequear boletos por salida",
        label: "Chequear boletos",
        icon: "how-to-reg-outline",
      },
    },
    {
      path: "/agencias/depositos/registrar",
      name: "agencia-depositos-registrar",
      component: () => import("@/features/agencia/RegistrarDepositoPage.vue"),
      meta: {
        title: "Registrar depósito de agencia",
        label: "Registrar depósito",
        icon: "savings-outline",
      },
    },
    {
      path: "/agencias/depositos",
      name: "agencia-depositos",
      component: () => import("@/features/agencia/DepositosPage.vue"),
      meta: {
        title: "Depósitos de agencia",
        label: "Buscar depósito",
        icon: "account-balance-outline",
      },
    },
    {
      path: "/agencias/saldos",
      name: "agencia-saldos",
      component: () => import("@/features/agencia/SaldosAgenciaPage.vue"),
      meta: {
        title: "Saldos de agencia",
        label: "Consultar saldos",
        icon: "account-balance-wallet-outline",
      },
    },
    {
      path: "/reporte/cuadre-venta-boletos",
      name: "reporte-cuadre-venta-boletos",
      component: () => import("@/features/reporte/CuadreVentaBoletosPage.vue"),
      meta: {
        title: "Cuadre de venta de boletos",
        label: "Cuadre de venta",
        icon: "request-quote-outline",
      },
    },
    {
      path: "/reporte/detalle-factura-boletos",
      name: "reporte-detalle-factura-boletos",
      component: () => import("@/features/reporte/DetalleFacturaBoletosPage.vue"),
      meta: {
        title: "Detalle de factura de boletos",
        label: "Detalle de facturas",
        icon: "receipt-long-outline",
      },
    },
    {
      path: "/reporte/cuadre-voucher",
      name: "reporte-cuadre-voucher",
      component: () => import("@/features/reporte/CuadreVoucherPage.vue"),
      meta: {
        title: "Cuadre de voucher",
        label: "Cuadre de voucher",
        icon: "confirmation-number-outline",
      },
    },
    {
      path: "/reporte/boleto-manifiesto",
      name: "reporte-boleto-manifiesto",
      component: () => import("@/features/reporte/ManifiestoSalidaPage.vue"),
      props: { tipo: "boleto" },
      meta: {
        title: "Boleto Manifiesto",
        label: "Boleto Manifiesto",
        icon: "description-outline",
      },
    },
    {
      path: "/reporte/boleto-manifiesto-interno",
      name: "reporte-boleto-manifiesto-interno",
      component: () => import("@/features/reporte/ManifiestoSalidaPage.vue"),
      props: { tipo: "interno" },
      meta: {
        title: "Boleto Manifiesto Interno",
        label: "Boleto Manifiesto Interno",
        icon: "assignment-outline",
      },
    },
    {
      path: "/reporte/boleto-manifiesto-piloto",
      name: "reporte-boleto-manifiesto-piloto",
      component: () => import("@/features/reporte/ManifiestoSalidaPage.vue"),
      props: { tipo: "piloto" },
      meta: {
        title: "Boleto Manifiesto Piloto",
        label: "Boleto Manifiesto Piloto",
        icon: "sports-motorsports-outline",
      },
    },
    {
      path: "/reporte/encomienda-manifiesto",
      name: "reporte-encomienda-manifiesto",
      component: () => import("@/features/reporte/ManifiestoEncomiendasPage.vue"),
      meta: {
        title: "Manifiesto de encomiendas",
        label: "Encomienda Manifiesto",
        icon: "local-shipping-outline",
      },
    },
    {
      path: "/reporte/encomienda-cuadre",
      name: "reporte-encomienda-cuadre",
      component: () => import("@/features/reporte/ReporteEncomiendaPage.vue"),
      props: { tipo: "cuadre" },
      meta: {
        title: "Cuadre de encomiendas",
        label: "Encomienda Cuadre",
        icon: "request-quote-outline",
      },
    },
    {
      path: "/reporte/encomienda-detalle-factura",
      name: "reporte-encomienda-detalle-factura",
      component: () => import("@/features/reporte/ReporteEncomiendaPage.vue"),
      props: { tipo: "detalle-factura" },
      meta: {
        title: "Detalle de factura de encomiendas",
        label: "Encomienda Detalle Factura",
        icon: "receipt-long-outline",
      },
    },
    {
      path: "/reporte/encomienda-detalle-general",
      name: "reporte-encomienda-detalle-general",
      component: () => import("@/features/reporte/ReporteEncomiendaPage.vue"),
      props: { tipo: "detalle-general" },
      meta: {
        title: "Detalle general de encomiendas",
        label: "Encomienda Detalle General",
        icon: "list-alt-outline",
      },
    },
    {
      path: "/reporte/encomienda-guia-interna",
      name: "reporte-encomienda-guia-interna",
      component: () => import("@/features/reporte/ReporteEncomiendaPage.vue"),
      props: { tipo: "guia-interna" },
      meta: {
        title: "Detalle de guías internas",
        label: "Encomienda Guía Interna",
        icon: "assignment-ind-outline",
      },
    },
    {
      path: "/reporte/encomienda-pendientes-entrega",
      name: "reporte-encomienda-pendientes-entrega",
      component: () => import("@/features/reporte/ReporteEncomiendaPage.vue"),
      props: { tipo: "pendientes-entrega" },
      meta: {
        title: "Encomiendas pendientes de entregar",
        label: "Encomienda Pendientes de Entrega",
        icon: "pending-actions",
      },
    },
    {
      // No empieza con /pagina: Caddy manda /pagina* a la página pública (backend).
      path: "/venta-en-linea",
      name: "venta-en-linea",
      component: () => import("@/features/pagina-web/PaginaWebPage.vue"),
      meta: {
        title: "Venta en línea (página web)",
        label: "Venta en línea",
        icon: "language",
      },
    },
    {
      path: "/chat/:canal(\\d+)?",
      name: "chat",
      component: () => import("@/features/chat/ChatPage.vue"),
      meta: {
        title: "Mensajes",
        label: "Mensajes",
        icon: "forum-outline",
        conservar: true,
      },
    },
    {
      path: "/lista/:entity?",
      name: "entity-list",
      props: true,
      component: () => import("@/features/entity-crud/ListPage.vue"),
      meta: {
        title: "Lista de entidad",
        label: "entidades",
      },
    },
    {
      path: "/form/:entity/:id?",
      name: "entity-form",
      props: true,
      component: () => import("@/features/entity-crud/FormPage.vue"),
      meta: {
        title: "Formulario de entidad",
      },
    },
    {
      path: "/:pathMatch(.*)*",
      name: "not-found",
      component: () => import("./NotFoundPage.vue"),
      meta: {
        layout: "blank",
        title: "Página no encontrada",
      },
    },
  ],
});

router.beforeEach((to, from) => {
  const session = useSessionStore();
  // DocumentDocument title
  const title = to.meta.title;
  document.title = title ? `${title} | FDN` : "FDN - Flotas de la Nación";

  // Auth guard
  const isPublic = to.meta.public === true;

  if (!session.isAuthenticated && !isPublic) {
    return loginHacia(to.fullPath);
  }

  if (session.isAuthenticated && isPublic) {
    return { name: "dashboard" };
  }

  // Chat en esquina o flotante: `/chat` abre la ventana sobre la página actual.
  // En el teléfono no hay ventanas (`esVentana` es falso): el chat es su página, a pantalla completa.
  const ventana = useVentanaChat();
  if (to.name === "chat" && ventana.esVentana) {
    const { canal, adjuntar } = destinoDeRuta(to.params, to.query);
    ventana.abrir(canal ?? ventana.canal, adjuntar);
    return from.matched.length ? false : { name: "dashboard" };
  }
});

router.afterEach((to) => {
  useNavigationHistoryStore().push(to);
  if (to.name !== "chat" && !to.meta.public) useVentanaChat().pagina = to.fullPath;
});
