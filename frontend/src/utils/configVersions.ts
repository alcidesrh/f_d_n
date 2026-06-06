const STORAGE_KEY = '_fdn_versions'

type VersionMap = Record<string, string>

export function getVersions(): VersionMap {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as VersionMap) : {}
  } catch {
    return {}
  }
}

export function setVersion(entityClass: string, timestamp: string): void {
  const versions = getVersions()
  versions[entityClass] = timestamp
  localStorage.setItem(STORAGE_KEY, JSON.stringify(versions))
}

export function removeVersion(entityClass: string): void {
  const versions = getVersions()
  delete versions[entityClass]
  localStorage.setItem(STORAGE_KEY, JSON.stringify(versions))
}

export function clearVersions(): void {
  localStorage.removeItem(STORAGE_KEY)
}

export function getVersion(entityClass: string): string | undefined {
  const versions = getVersions()
  return versions[entityClass]
}
