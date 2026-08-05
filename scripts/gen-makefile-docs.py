#!/usr/bin/env python3
"""
Generate Makefile documentation from Makefile help parsing.
Creates: docs/makefile/*.md
"""
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).parent.parent
MAKEFILE = ROOT / "Makefile"
OUTPUT_DIR = ROOT / "docs" / "makefile"

CATEGORIES = {
    "docker": ["up", "build", "down", "check", "sh", "sqlserver", "kill", "start", "stop", "restart", "logs", "bash", "commands", "b", "d", "db_port", "debug-build", "debug", "dev", "restart_debug", "prod", "prod-build", "sf_sqlserver", "schema", "frontend-fdn-quasar-restart", "migrar", "clean"],
    "backend": ["cc", "warmup", "migration", "migrate", "entity-setup", "entity", "migrar-entities", "migrar-todo", "sincronizar", "reset-db", "test", "testf"],
    "frontend": [],  # No frontend targets in root Makefile
    "docs": ["docs", "docs-serve", "docs-clean", "docs-gen-dirtree"],
    "other": ["stats"]
}

def parse_makefile() -> dict:
    """Parse Makefile and extract targets with descriptions."""
    content = MAKEFILE.read_text(encoding="utf-8")
    
    targets = {}
    current_category = "other"
    
    for line in content.split("\n"):
        # Category comments
        if line.startswith("## —") or line.startswith("## --"):
            cat_name = line.replace("##", "").replace("—", "").replace("-", "").strip().lower()
            if "docker" in cat_name:
                current_category = "docker"
            elif "backend" in cat_name or "symfony" in cat_name:
                current_category = "backend"
            elif "frontend" in cat_name:
                current_category = "frontend"
            elif "propio" in cat_name:
                current_category = "other"
            elif "doc" in cat_name:
                current_category = "docs"
            continue
        
        # Target with description: target: ## description
        match = re.match(r"^([a-zA-Z0-9_-]+):\s+##\s*(.*)$", line)
        if match:
            target = match.group(1)
            desc = match.group(2).strip()
            targets[target] = {
                "description": desc,
                "category": current_category
            }
    
    return targets

def generate_category_md(category: str, targets: dict) -> str:
    cat_targets = {k: v for k, v in targets.items() if v["category"] == category}
    
    if not cat_targets:
        return f"# {category.capitalize()}\n\n_No targets in this category._\n"
    
    lines = [f"# Makefile — {category.capitalize()}", ""]
    lines.append("| Comando | Descripción |")
    lines.append("|---------|-------------|")
    
    for target in sorted(cat_targets.keys()):
        desc = cat_targets[target]["description"]
        lines.append(f"| `make {target}` | {desc} |")
    
    return "\n".join(lines)

def generate_overview_md(targets: dict) -> str:
    lines = ["# Makefile — Overview", ""]
    lines.append("El `Makefile` raíz orquesta todo el stack: Docker, Backend (Symfony), Frontend (via Docker) y Documentación.")
    lines.append("")
    lines.append("## Categorías")
    lines.append("")
    
    for cat in ["docker", "backend", "frontend", "docs", "other"]:
        count = len([t for t in targets.values() if t["category"] == cat])
        if count > 0:
            lines.append(f"- **{cat.capitalize()}**: {count} comandos → [`{cat}.md`]({cat}.md)")
    
    lines.append("")
    lines.append("## Uso Rápido")
    lines.append("")
    lines.append("```bash")
    lines.append("# Ver todos los targets")
    lines.append("make help")
    lines.append("")
    lines.append("# Stack completo")
    lines.append("make dev          # Desarrollo")
    lines.append("make debug        # Con Xdebug")
    lines.append("make b            # Rebuild imágenes")
    lines.append("make d            # Parar y limpiar")
    lines.append("")
    lines.append("# Backend")
    lines.append("make cc           # Limpiar caché")
    lines.append("make migrate      # Ejecutar migraciones")
    lines.append("make test         # Tests")
    lines.append("")
    lines.append("# Documentación")
    lines.append("make docs-serve   # Servir en localhost:8000")
    lines.append("```")
    
    return "\n".join(lines)

def main():
    print("Parsing Makefile...")
    targets = parse_makefile()
    print(f"Found {len(targets)} targets")
    
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    
    # Overview
    (OUTPUT_DIR / "overview.md").write_text(generate_overview_md(targets), encoding="utf-8")
    
    # Per category
    for cat in ["docker", "backend", "frontend", "docs", "other"]:
        content = generate_category_md(cat, targets)
        (OUTPUT_DIR / f"{cat}.md").write_text(content, encoding="utf-8")
        print(f"✅ Generated makefile/{cat}.md")

if __name__ == "__main__":
    main()