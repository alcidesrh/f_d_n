# Makefile — Overview

El `Makefile` raíz orquesta todo el stack: Docker, Backend (Symfony), Frontend (via Docker) y Documentación.

## Categorías

- **Docker**: 13 comandos → [`docker.md`](docker.md)
- **Docs**: 8 comandos → [`docs.md`](docs.md)
- **Other**: 14 comandos → [`other.md`](other.md)

## Uso Rápido

```bash
# Ver todos los targets
make help

# Stack completo
make dev          # Desarrollo
make debug        # Con Xdebug
make b            # Rebuild imágenes
make d            # Parar y limpiar

# Backend
make cc           # Limpiar caché
make migrate      # Ejecutar migraciones
make test         # Tests

# Documentación
make docs-serve   # Servir en localhost:8000
```