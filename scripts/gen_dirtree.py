#!/usr/bin/env python3
"""
Generate directory tree documentation.
"""
import os
from pathlib import Path

ROOT = Path(__file__).parent.parent.parent
OUTPUT_FILE = ROOT / "docs" / "docs" / "directory-structure.md"

EXCLUDE_DIRS = {'.git', '.venv', 'node_modules', 'vendor', 'var', 'site', '__pycache__', '.pytest_cache', '.phpunit.cache', 'TerminalOmnibus', 'dist', 'build', '.quasar'}
EXCLUDE_FILES = {'.gitignore', '.editorconfig', '.env', '.env.*', 'composer.lock', 'package-lock.json', 'pnpm-lock.yaml', 'yarn.lock', '*.log', '*.png', '*.jpg', '*.jpeg', '*.ico', '*.svg', '*.woff', '*.woff2'}

def should_exclude(path: Path) -> bool:
    parts = set(path.parts)
    if parts & EXCLUDE_DIRS:
        return True
    name = path.name
    for pattern in EXCLUDE_FILES:
        if '*' in pattern:
            import fnmatch
            if fnmatch.fnmatch(name, pattern):
                return True
        elif name == pattern:
            return True
    return False

def generate_tree(root: Path, prefix: str = "", max_depth: int = 4, current_depth: int = 0) -> list:
    if current_depth >= max_depth:
        return []
    
    lines = []
    try:
        entries = sorted([e for e in root.iterdir() if not should_exclude(e)], key=lambda e: (e.is_file(), e.name.lower()))
    except PermissionError:
        return lines
    
    for i, entry in enumerate(entries):
        is_last = i == len(entries) - 1
        connector = "└── " if is_last else "├── "
        lines.append(f"{prefix}{connector}{entry.name}/" if entry.is_dir() else f"{prefix}{connector}{entry.name}")
        
        if entry.is_dir():
            extension = "    " if is_last else "│   "
            lines.extend(generate_tree(entry, prefix + extension, max_depth, current_depth + 1))
    
    return lines

def main():
    print(f"Generating directory tree from {ROOT}")
    
    tree_lines = generate_tree(ROOT)
    
    content = f"""# Estructura del Proyecto

> Generado automáticamente: `make docs-gen-dirtree`
> Raíz: `{ROOT.name}`

```text
{ROOT.name}/
{chr(10).join(tree_lines)}
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
"""

    OUTPUT_FILE.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT_FILE.write_text(content, encoding="utf-8")
    print(f"✅ Generated {OUTPUT_FILE}")

if __name__ == "__main__":
    main()