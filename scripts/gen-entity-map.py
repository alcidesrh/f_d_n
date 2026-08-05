#!/usr/bin/env python3
"""
Generate entity documentation per subdomain from Doctrine entities.
Creates: docs/backend/subdomains/{transporte,flota,venta,...}/entities.md
"""
import re
from pathlib import Path
from typing import Dict, List, Optional

ROOT = Path(__file__).parent.parent
ENTITY_DIR = ROOT / "backend" / "src" / "Entity"
OUTPUT_BASE = ROOT / "docs" / "backend" / "subdomains"

SUBDOMAINS = {
    "transporte": ["Boleto", "Trayecto", "Recorrido", "RecorridoMatrioska", "Servicio", "Venta", "Factura", "Cliente", "Status"],
    "flota": ["Bus", "Marca", "Asiento", "Piloto", "Enclave", "BusMarca"],
    "venta": ["Venta", "Factura", "Cliente", "Boleto", "Servicio"],
    "personal": ["Usuario", "Piloto"],
    "configuracion": ["EntityConfiguration", "CollectionFieldConfig", "FormFieldConfig"],
    "infraestructura": ["Icon", "IconCategory", "Empresa", "Localidad", "Estacion", "Parada", "Nacion"],
    "seguridad": ["Usuario", "Role", "Permission", "Action", "ApiToken"],
}

def get_subdomain(entity: str) -> List[str]:
    """Return list of subdomains this entity belongs to."""
    domains = []
    for domain, entities in SUBDOMAINS.items():
        if entity in entities:
            domains.append(domain)
    return domains or ["general"]

def parse_entity_file(filepath: Path) -> Optional[Dict]:
    content = filepath.read_text(encoding="utf-8")
    
    if not re.search(r'#\[ORM\\Entity[^\]]*\]', content) and "@ORM\\Entity" not in content:
        return None
    
    class_match = re.search(r"class\s+(\w+)", content)
    if not class_match:
        return None
    class_name = class_match.group(1)
    
    table_match = re.search(r'#\[ORM\\Table\(name=["\'](\w+)["\']', content)
    if not table_match:
        table_match = re.search(r"@ORM\\Table\(name=['\"](\w+)['\"]", content)
    table_name = table_match.group(1) if table_match else class_name.lower()
    
    # Docblock
    docblock = ""
    class_pos = content.find(f"class {class_name}")
    if class_pos > 0:
        before = content[:class_pos]
        docblock_match = re.search(r"/\*\*(.*?)\*/\s*$", before, re.DOTALL)
        if docblock_match:
            docblock = docblock_match.group(1).strip()
    
    columns = []
    col_pattern = r'#\[ORM\\Column\(([^)]+)\)\]\s*(?:private|protected|public)\s+(\w+)'
    for match in re.finditer(col_pattern, content):
        attrs = match.group(1)
        col_name = match.group(2)
        type_match = re.search(r'type=["\'](\w+)["\']', attrs)
        col_type = type_match.group(1) if type_match else "string"
        length_match = re.search(r'length=(\d+)', attrs)
        length = f"({length_match.group(1)})" if length_match else ""
        nullable = "nullable=true" in attrs or "nullable: true" in attrs
        is_id = "#[ORM\\Id]" in content[max(0, match.start()-200):match.start()]
        columns.append({
            "name": col_name,
            "type": f"{col_type}{length}",
            "nullable": nullable,
            "is_id": is_id
        })
    
    relations = []
    # ManyToOne
    for match in re.finditer(r'#\[ORM\\ManyToOne\(([^)]*)\)\]\s*(?:private|protected|public)\s+(\w+)', content):
        attrs = match.group(1)
        field = match.group(2)
        target_match = re.search(r'targetEntity=["\'](\w+)["\']', attrs)
        target = target_match.group(1) if target_match else field
        inv_match = re.search(r'inversedBy=["\'](\w+)["\']', attrs)
        inversed = inv_match.group(1) if inv_match else None
        join_match = re.search(r'joinColumns=\[.*?name=["\'](\w+)["\']', attrs, re.DOTALL)
        join_col = join_match.group(1) if join_match else f"{field}_id"
        relations.append({
            "type": "ManyToOne",
            "field": field,
            "target": target,
            "inversed_by": inversed,
            "join_column": join_col
        })
    
    # OneToMany
    for match in re.finditer(r'#\[ORM\\OneToMany\(([^)]*)\)\]\s*(?:private|protected|public)\s+(\w+)', content):
        attrs = match.group(1)
        field = match.group(2)
        target_match = re.search(r'targetEntity=["\'](\w+)["\']', attrs)
        target = target_match.group(1) if target_match else field
        mapped_match = re.search(r'mappedBy=["\'](\w+)["\']', attrs)
        mapped = mapped_match.group(1) if mapped_match else None
        relations.append({
            "type": "OneToMany",
            "field": field,
            "target": target,
            "mapped_by": mapped
        })
    
    # OneToOne
    for match in re.finditer(r'#\[ORM\\OneToOne\(([^)]*)\)\]\s*(?:private|protected|public)\s+(\w+)', content):
        attrs = match.group(1)
        field = match.group(2)
        target_match = re.search(r'targetEntity=["\'](\w+)["\']', attrs)
        target = target_match.group(1) if target_match else field
        relations.append({
            "type": "OneToOne",
            "field": field,
            "target": target
        })
    
    # ManyToMany
    for match in re.finditer(r'#\[ORM\\ManyToMany\(([^)]*)\)\]\s*(?:private|protected|public)\s+(\w+)', content):
        attrs = match.group(1)
        field = match.group(2)
        target_match = re.search(r'targetEntity=["\'](\w+)["\']', attrs)
        target = target_match.group(1) if target_match else field
        relations.append({
            "type": "ManyToMany",
            "field": field,
            "target": target
        })
    
    # Embeddables
    embeds = re.findall(r'#\[ORM\\Embedded\(class="(\w+)"\)\]', content)
    
    return {
        "class": class_name,
        "table": table_name,
        "docblock": docblock,
        "columns": columns,
        "relations": relations,
        "embeds": embeds,
        "subdomains": get_subdomain(class_name)
    }

def generate_entity_md(entity: Dict) -> str:
    lines = [f"## {entity['class']}", ""]
    
    if entity["docblock"]:
        lines.append(entity["docblock"])
        lines.append("")
    
    lines.append(f"**Tabla**: `{entity['table']}`  ")
    lines.append(f"**Subdominios**: {', '.join(entity['subdomains'])}  ")
    lines.append("")
    
    # Columns
    if entity["columns"]:
        lines.append("### Columnas")
        lines.append("")
        lines.append("| Columna | Tipo | PK | Nullable |")
        lines.append("|---------|------|----|----------|")
        for col in entity["columns"]:
            pk = "✅" if col["is_id"] else ""
            null = "✅" if col["nullable"] else "❌"
            lines.append(f"| `{col['name']}` | {col['type']} | {pk} | {null} |")
        lines.append("")
    
    # Relations
    if entity["relations"]:
        lines.append("### Relaciones")
        lines.append("")
        for rel in entity["relations"]:
            if rel["type"] == "ManyToOne":
                lines.append(f"- **{rel['field']}** → `{rel['target']}` (ManyToOne)")
                if rel["inversed_by"]:
                    lines.append(f"  - Inverso: `{rel['inversed_by']}`")
                lines.append(f"  - FK: `{rel['join_column']}`")
            elif rel["type"] == "OneToMany":
                lines.append(f"- **{rel['field']}** → `{rel['target']}` (OneToMany)")
                if rel["mapped_by"]:
                    lines.append(f"  - Mapped by: `{rel['mapped_by']}`")
            elif rel["type"] == "OneToOne":
                lines.append(f"- **{rel['field']}** → `{rel['target']}` (OneToOne)")
            elif rel["type"] == "ManyToMany":
                lines.append(f"- **{rel['field']}** ↔ `{rel['target']}` (ManyToMany)")
        lines.append("")
    
    if entity["embeds"]:
        lines.append("### Embebidos")
        lines.append("")
        for emb in entity["embeds"]:
            lines.append(f"- `{emb}`")
        lines.append("")
    
    return "\n".join(lines)

def main():
    print(f"Scanning entities in {ENTITY_DIR}")
    
    entities = []
    for php_file in ENTITY_DIR.rglob("*.php"):
        if php_file.name.startswith(".") or "Base" in php_file.parts:
            continue
        parsed = parse_entity_file(php_file)
        if parsed:
            entities.append(parsed)
            print(f"  ✓ {parsed['class']}")
    
    # Group by subdomain
    by_domain = {}
    for entity in entities:
        for domain in entity["subdomains"]:
            if domain not in by_domain:
                by_domain[domain] = []
            by_domain[domain].append(entity)
    
    # Generate per subdomain
    for domain, ents in by_domain.items():
        domain_dir = OUTPUT_BASE / domain
        domain_dir.mkdir(parents=True, exist_ok=True)
        
        output_file = domain_dir / "entities.md"
        
        content = f"""# Entidades — {domain.capitalize()}

> Generado automáticamente: `make docs-gen-entity-map`
> Total entidades en este subdominio: {len(ents)}

"""
        for entity in sorted(ents, key=lambda e: e["class"]):
            content += generate_entity_md(entity)
            content += "\n---\n\n"
        
        output_file.write_text(content, encoding="utf-8")
        print(f"✅ Generated {output_file}")
    
    # Also generate index
    index_file = OUTPUT_BASE / "overview.md"
    index_content = """# Subdominios del Backend

> Mapa mental de bounded contexts. Cada subdominio agrupa entidades con responsabilidad cohesiva.

## Subdominios

"""
    for domain in sorted(by_domain.keys()):
        count = len(by_domain[domain])
        index_content += f"- [{domain.capitalize()}]({domain}/index.md) — {count} entidades\n"
    
    index_file.write_text(index_content, encoding="utf-8")
    print(f"✅ Generated {index_file}")

if __name__ == "__main__":
    main()