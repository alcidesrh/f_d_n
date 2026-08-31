#!/usr/bin/env python3
import json
import os
import subprocess

OUTPUT_FILE = "CODEBASE_MAP.md"

# Mapeo de patrones según el tipo de archivo (Soporta TypeScript/JavaScript y PHP)
PATTERNS = [
    {
        "category": "📐 Tipos e Interfaces (TypeScript)",
        "lang": "ts",
        "pattern": "export interface $NAME { $$$ }",
        "type": "interface",
    },
    {
        "category": "🏷️ Tipos Definidos (TypeScript)",
        "lang": "ts",
        "pattern": "export type $NAME = $VALUE",
        "type": "type",
    },
    {
        "category": "⚡ Composables & Funciones (Vue/JS)",
        "lang": "ts",
        "pattern": "export function $NAME($$$) { $$$ }",
        "type": "function",
    },
    {
        "category": "🐘 Clases y Servicios (PHP)",
        "lang": "php",
        "pattern": "class $NAME { $$$ }",
        "type": "class",
    },
]


def run_ast_grep(pattern, lang):
    """Ejecuta ast-grep en la carpeta src/ y devuelve resultados estructurados."""
    cmd = [
        "ast-grep",
        "run",
        "--pattern",
        pattern,
        "--lang",
        lang,
        "--json",
        "frontend",
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        return json.loads(result.stdout) if result.stdout else []
    except Exception:
        return []


def generate_markdown():
    markdown = ["# 🗺️ Mapa Semántico de la Base de Código\n"]
    markdown.append(
        "> Este archivo mapea la estructura de `src/`. Haz clic en los enlaces para ir directo al código en Zed.\n"
    )

    for item in PATTERNS:
        matches = run_ast_grep(item["pattern"], item["lang"])
        if not matches:
            continue

        markdown.append(f"## {item['category']}\n")

        # Agrupar por archivo
        files_map = {}
        for match in matches:
            file_path = match.get("file", "")
            start_line = match.get("range", {}).get("start", {}).get("line", 0) + 1
            var_name = (
                match.get("metaVariables", {}).get("NAME", {}).get("text", "Sin nombre")
            )

            if file_path not in files_map:
                files_map[file_path] = []

            files_map[file_path].append((var_name, start_line))

        # Escribir estructura limpia
        for file_path, symbols in files_map.items():
            markdown.append(f"### `{file_path}`")
            for name, line in symbols:
                # Generar enlace compatible con Zed/VSCode
                markdown.append(f"- **[{name}]({file_path}#L{line})** *(Línea {line})*")
            markdown.append("")

    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        f.write("\n".join(markdown))

    print(f"✅ ¡Mapa generado con éxito en `{OUTPUT_FILE}`!")


if __name__ == "__main__":
    generate_markdown()
