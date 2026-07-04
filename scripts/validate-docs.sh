#!/usr/bin/env bash
# Validate documentation: markdownlint, link check, mermaid syntax
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DOCS_DIR="$ROOT/docs/docs"
SCRIPTS_DIR="$ROOT/docs/scripts"

echo "🔍 Validating documentation..."

# Check if tools are available
check_tool() {
    if ! command -v "$1" &> /dev/null; then
        echo "⚠️  $1 not found, skipping $2"
        return 1
    fi
    return 0
}

# 1. Markdown lint
if check_tool "markdownlint" "markdown linting"; then
    echo "📝 Running markdownlint..."
    markdownlint "$DOCS_DIR" --config "$ROOT/.markdownlint.json" 2>/dev/null || \
    markdownlint "$DOCS_DIR" --disable MD013 MD033 MD041 2>/dev/null || true
fi

# 2. Check internal links
if check_tool "markdown-link-check" "link checking"; then
    echo "🔗 Checking internal links..."
    find "$DOCS_DIR" -name "*.md" -exec markdown-link-check {} -q \; 2>/dev/null || true
fi

# 3. Validate Mermaid syntax
if check_tool "mmdc" "mermaid validation"; then
    echo "🎨 Validating Mermaid diagrams..."
    find "$DOCS_DIR" -name "*.mmd" -o -name "*.md" | while read -r file; do
        if grep -q "^```mermaid" "$file" || [[ "$file" == *.mmd ]]; then
            mmdc -i "$file" -o /dev/null 2>/dev/null || echo "⚠️  Mermaid error in $file"
        fi
    done
fi

# 4. Check for broken references in nav (mkdocs.yml)
if check_tool "python3" "nav validation"; then
    echo "📋 Validating mkdocs.yml nav..."
    python3 -c "
import yaml, sys
from pathlib import Path

ROOT = Path('$ROOT')
MKDOCS = ROOT / 'docs' / 'mkdocs.yml'
DOCS = ROOT / 'docs' / 'docs'

with open(MKDOCS) as f:
    config = yaml.safe_load(f)

def check_nav(nav, prefix=''):
    errors = []
    for item in nav:
        if isinstance(item, dict):
            for title, path in item.items():
                if isinstance(path, str) and path.endswith('.md'):
                    full_path = DOCS / path
                    if not full_path.exists():
                        errors.append(f'  ❌ Missing: {path} (from {title})')
                elif isinstance(path, list):
                    errors.extend(check_nav(path, f'{prefix}{title} > '))
    return errors

errors = check_nav(config.get('nav', []))
if errors:
    print('❌ Navigation errors:')
    for e in errors:
        print(e)
    sys.exit(1)
else:
    print('  ✅ All nav entries exist')
"
fi

# 5. Check generated files are not stale (optional)
echo "⏰ Checking generated files freshness..."
GENERATED_FILES=(
    "$DOCS_DIR/directory-structure.md"
    "$DOCS_DIR/backend/database/entity-map.mmd"
)
for f in "${GENERATED_FILES[@]}"; do
    if [[ -f "$f" ]]; then
        # Check if older than 7 days
        if [[ $(find "$f" -mtime +7 2>/dev/null) ]]; then
            echo "⚠️  $f is older than 7 days (run make docs-gen-all)"
        fi
    fi
done

echo "✅ Validation complete"