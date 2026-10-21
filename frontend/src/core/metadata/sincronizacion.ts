/**
 * Mantiene al día la metadata que el navegador guarda (ADR-036): el schema
 * GraphQL (`useSchemaStore`) y la configuración de cada entidad (stores
 * `entity:*`). Se entera de una revisión nueva por Mercure (`TOPICO`), por la
 * cabecera `X-Metadata-Revision` de cualquier respuesta, al volver a la
 * pestaña y por los errores de GraphQL que delatan un schema viejo. Entonces
 * pide el manifiesto (304 si nada cambió) y:
 *
 * - si el schema del servidor es otro, vuelve a pedir la introspección; las
 *   stores de las entidades que cambiaron se ajustan y las de las que ya no
 *   existen se olvidan;
 * - las stores en uso cuya configuración quedó vieja suben su versión
 *   (`versionDe`): las pantallas abiertas se recargan; las demás se ponen al
 *   día cuando se usan (`init`);
 * - si la revisión se publicó con aviso, ofrece «Recargar» con la nota.
 *
 * Avisos seguidos se juntan en una sola consulta (`ESPERA`, `MAXIMO`).
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useSessionStore } from '@/core/auth/session'
import { entityStores, olvidarEntidad } from '@/core/entities/registry'
import { useSchemaStore } from '@/core/entities/schema'
import { alDesfasarseElEsquema } from '@/core/graphql/desfase'
import { notify } from '@/core/notify'
import { suscribir } from '@/core/realtime'
import { observarRespuestas } from '@/core/respuestas'
import { crearAgenda } from './agenda'
import {
  CABECERA_REVISION,
  fetchManifiesto,
  manifiestoActual,
  revisionVigenteDeConfig,
  type AvisoMetadata,
  type Manifiesto,
} from './manifiesto'

/** Tópico público de Mercure: el aviso solo trae el número de la revisión. */
export const TOPICO = '/metadata'

/** Avisos seguidos: se espera este silencio, y como mucho el máximo desde el primero. */
const ESPERA = 1000
const MAXIMO = 5000
/** Al arrancar se espera el manifiesto hasta esto; si tarda más, se aplica cuando llegue. */
const LIMITE_ARRANQUE = 1500
/** Todos los navegadores reciben el aviso a la vez: antes de una introspección cada uno espera al azar hasta esto. */
const ESCALONADO = 5000
/** Al volver a la pestaña se consulta si la última consulta es más vieja que esto. */
const VOLVER = 60_000
/** La red de seguridad pide la introspección como mucho una vez en este tiempo. */
const DESFASE = 60_000

const esperar = (ms: number) => new Promise<void>((resolver) => setTimeout(resolver, ms))

function revisionDe(datos: unknown): number | undefined {
  const numero = Number((datos as { revision?: unknown } | null)?.revision)
  return Number.isInteger(numero) && numero > 0 ? numero : undefined
}

export interface OpcionesRevision {
  /** Pedir la introspección aunque el manifiesto diga que el schema es el mismo (red de seguridad). */
  forzarEsquema?: boolean
  /** Esperar al azar antes de la introspección. */
  escalonar?: boolean
}

export const useMetadataStore = defineStore(
  'metadata',
  () => {
    /** Revisión del último manifiesto aplicado en esta página; null: ninguno. */
    const revision = ref<number | null>(null)
    /** Por entidad, sube cuando cambió su metadata: las pantallas abiertas lo miran. */
    const versiones = ref<Record<string, number>>({})
    /** Revisión publicada con aviso que espera «Recargar» o «Después». */
    const aviso = ref<AvisoMetadata | null>(null)

    let enCurso: Promise<void> | null = null
    let otraVez = false
    let ultimaConsulta = 0
    let ultimoDesfase = 0
    let detenerEscucha: (() => void) | null = null
    /** Lo anunciado desde la última consulta: la revisión más alta y si hubo un aviso sin número. */
    let anunciada = 0
    let sinNumero = false

    // Al vencer la espera puede que ya esté aplicado (otra respuesta lo trajo mientras se consultaba).
    const agenda = crearAgenda(
      () => {
        const falta = sinNumero || revision.value === null || anunciada > revision.value
        anunciada = 0
        sinNumero = false
        if (falta) void revisar({ escalonar: true })
      },
      { espera: ESPERA, maximo: MAXIMO },
    )

    function versionDe(entidad: string): number {
      return versiones.value[entidad] ?? 0
    }

    function marcar(entidades: Iterable<string>) {
      const siguientes = { ...versiones.value }
      for (const nombre of entidades) siguientes[nombre] = (siguientes[nombre] ?? 0) + 1
      versiones.value = siguientes
    }

    async function leer(): Promise<Manifiesto | null> {
      try {
        const manifiesto = await fetchManifiesto()
        ultimaConsulta = Date.now()
        return manifiesto
      } catch (error) {
        // Sin manifiesto se sigue con lo guardado (y la próxima respuesta trae la cabecera).
        console.warn('[metadata] no se pudo leer el manifiesto:', error)
        return null
      }
    }

    /** Pone el navegador al día con el manifiesto; devuelve si cambió el schema de alguna entidad. */
    async function aplicar(manifiesto: Manifiesto, opciones: OpcionesRevision = {}): Promise<boolean> {
      const schema = useSchemaStore()
      const anterior = revision.value
      const cambiadas = new Set<string>()
      let esquemaCambio = false
      if (opciones.forzarEsquema || schema.desactualizado(manifiesto)) {
        if (opciones.escalonar) await esperar(Math.random() * ESCALONADO)
        const diferencias = await schema.recargar(manifiesto)
        if (diferencias) {
          for (const nombre of diferencias.eliminadas) olvidarEntidad(nombre)
          for (const nombre of [...diferencias.agregadas, ...diferencias.modificadas]) cambiadas.add(nombre)
          esquemaCambio = cambiadas.size > 0 || diferencias.eliminadas.length > 0
        }
      }
      // Después del schema: hasta acá las stores seguían con el manifiesto anterior.
      manifiestoActual.value = manifiesto
      for (const [nombre, store] of entityStores) {
        const vigente = revisionVigenteDeConfig(nombre, manifiesto) ?? 0
        if (store.revisionConfig !== null && store.revisionConfig < vigente) cambiadas.add(nombre)
      }
      for (const nombre of cambiadas) entityStores.get(nombre)?.reconciliar()
      if (cambiadas.size > 0) marcar(cambiadas)
      if (anterior !== null && manifiesto.aviso && manifiesto.aviso.revision > anterior) aviso.value = manifiesto.aviso
      revision.value = manifiesto.revision
      return esquemaCambio
    }

    /** Pide el manifiesto y lo aplica. Una consulta a la vez: lo que se pida mientras, al terminar. */
    function revisar(opciones: OpcionesRevision = {}): Promise<void> {
      if (!useSessionStore().isAuthenticated) return Promise.resolve()
      if (enCurso) {
        otraVez = true
        return enCurso
      }
      enCurso = (async () => {
        const manifiesto = await leer()
        if (manifiesto) await aplicar(manifiesto, opciones)
      })().finally(() => {
        enCurso = null
        if (otraVez) {
          otraVez = false
          void revisar()
        }
      })
      return enCurso
    }

    /**
     * Antes de montar la app: con sesión, el manifiesto decide si el schema
     * guardado sigue vigente. Si tarda, la app arranca con lo guardado y el
     * manifiesto se aplica cuando llegue.
     */
    async function arrancar(): Promise<void> {
      let manifiesto: Manifiesto | null = null
      if (useSessionStore().isAuthenticated) {
        const pedido = leer()
        manifiesto = await Promise.race([pedido, esperar(LIMITE_ARRANQUE).then(() => null)])
        if (!manifiesto) {
          void pedido.then((tarde) => {
            if (tarde) void aplicar(tarde)
          })
        }
      }
      await useSchemaStore().init(manifiesto)
      if (manifiesto) {
        manifiestoActual.value = manifiesto
        revision.value = manifiesto.revision
      }
    }

    /**
     * Un error de GraphQL delató un schema viejo: se vuelve a pedir aunque el
     * manifiesto no lo diga (como mucho una vez por minuto). Las pantallas
     * abiertas se recargan solas; lo que falló al guardar hay que repetirlo.
     */
    async function atenderDesfase() {
      if (Date.now() - ultimoDesfase < DESFASE || !useSessionStore().isAuthenticated) return
      ultimoDesfase = Date.now()
      await enCurso
      const manifiesto = await leer()
      if (manifiesto && (await aplicar(manifiesto, { forzarEsquema: true })))
        notify.info('Se actualizó la definición de la API. Si algo no se guardó, vuelve a intentarlo.')
    }

    /** Hay otra revisión (sin número: no se sabe cuál). Se junta con los avisos que sigan. */
    function programar(avisada?: number) {
      if (avisada !== undefined && revision.value !== null && avisada <= revision.value) return
      if (avisada === undefined) sinNumero = true
      else anunciada = Math.max(anunciada, avisada)
      agenda.programar()
    }

    /** Escucha los avisos mientras hay sesión (lo llama el shell al entrar). */
    function iniciar() {
      detener()
      const cerrar = suscribir(TOPICO, (datos) => programar(revisionDe(datos)))
      const dejarDeObservar = observarRespuestas((respuesta) => {
        const numero = revisionDe({ revision: respuesta.headers.get(CABECERA_REVISION) })
        if (numero !== undefined) programar(numero)
      })
      const alVolver = () => {
        if (document.visibilityState === 'visible' && Date.now() - ultimaConsulta > VOLVER) programar()
      }
      document.addEventListener('visibilitychange', alVolver)
      const quitarDesfase = alDesfasarseElEsquema(() => void atenderDesfase())
      detenerEscucha = () => {
        cerrar()
        dejarDeObservar()
        document.removeEventListener('visibilitychange', alVolver)
        quitarDesfase()
        agenda.cancelar()
      }
    }

    function detener() {
      detenerEscucha?.()
      detenerEscucha = null
    }

    function descartarAviso() {
      aviso.value = null
    }

    return { revision, versiones, aviso, versionDe, arrancar, revisar, aplicar, programar, iniciar, detener, descartarAviso }
  },
  { global: true },
)
