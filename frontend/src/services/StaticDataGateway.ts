type GatewayHandler = (payload: Record<string, unknown>) => void | Promise<void>

const MERCURE_HUB_URL = () => {
  const base = config.ENTRYPOINT.replace('/api', '')
  return `${base}/.well-known/mercure`
}

export class StaticDataGateway {
  private handlers = new Map<string, GatewayHandler[]>()
  private sources = new Map<string, EventSource>()
  private started = false

  register(topic: string, handler: GatewayHandler): void {
    const handlers = this.handlers.get(topic) || []
    handlers.push(handler)
    this.handlers.set(topic, handlers)
    if (this.started) {
      this.connect(topic)
    }
  }

  start(): void {
    if (this.started) return
    this.started = true
    for (const topic of this.handlers.keys()) {
      this.connect(topic)
    }
  }

  stop(): void {
    this.started = false
    for (const [_, source] of this.sources) {
      source.close()
    }
    this.sources.clear()
  }

  private connect(topic: string): void {
    const url = new URL(MERCURE_HUB_URL())
    url.searchParams.append('topic', topic)

    const source = new EventSource(url.toString())
    this.sources.set(topic, source)

    source.addEventListener('message', (event) => {
      try {
        const payload = JSON.parse(event.data) as Record<string, unknown>
        const handlers = this.handlers.get(topic)
        if (handlers) {
          for (const handler of handlers) {
            void Promise.resolve(handler(payload))
          }
        }
      } catch {
      }
    })

    source.addEventListener('error', () => {
      source.close()
      this.sources.delete(topic)
      if (this.started) {
        setTimeout(() => this.connect(topic), 5000)
      }
    })
  }
}

let instance: StaticDataGateway | null = null

export function useStaticDataGateway(): StaticDataGateway {
  if (!instance) {
    instance = new StaticDataGateway()
  }
  return instance
}

export function destroyStaticDataGateway(): void {
  instance?.stop()
  instance = null
}
