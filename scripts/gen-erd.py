#!/usr/bin/env python3
"""
Generate Mermaid ERD from Doctrine entities.
Scans backend/src/Entity/ for #[ORM\Entity] classes and their #[ORM\Column], #[ORM\ManyToOne], #[ORM\OneToMany], etc.
Outputs: docs/docs/backend/database/entity-map.mmd
"""
import re
import os
from pathlib import Path
from typing import Dict, List, Tuple, Optional

ROOT = Path(__file__).parent.parent.parent  # modelo/
ENTITY_DIR = ROOT / "backend" / "src" / "Entity"
OUTPUT_FILE = ROOT / "docs" / "docs" / "backend" / "database" / "entity-map.mmd"

# Subdominios para agrupar
SUBDOMAINS = {
    "Transporte": ["Boleto", "Trayecto", "Recorrido", "RecorridoMatrioska", "Servicio", "Venta", "Factura", "Cliente", "Status"],
    "Flota": ["Bus", "Marca", "Asiento", "Piloto", "Enclave", "BusMarca"],
    "Personal": ["Usuario", "Piloto"],  # Piloto aparece en ambos
    "Organizacion": ["Empresa", "Localidad", "Estacion", "Parada", "Nacion"],
    "Configuracion": ["EntityConfiguration", "CollectionFieldConfig", "FormFieldConfig"],
    "Seguridad": ["Usuario", "Role", "Permission", "Action", "ApiToken"],
    "Infraestructura": ["Icon", "IconCategory"],
    "Base": ["Action"],  # Action base
}

def get_subdomain(entity: str) -> str:
    for domain, entities in SUBDOMAINS.items():
        if entity in entities:
            return domain
    return "Otros"

def parse_entity_file(filepath: Path) -> Optional[Dict]:
    """Parse a Doctrine entity PHP file."""
    content = filepath.read_text(encoding="utf-8")
    
    # Check if it's an entity
    if not re.search(r'#\[ORM\\Entity[^\]]*\]', content) and "@ORM\\Entity" not in content:
        return None
    
    # Extract class name
    class_match = re.search(r"class\s+(\w+)", content)
    if not class_match:
        return None
    class_name = class_match.group(1)
    
    # Extract table name
    table_match = re.search(r"#\[ORM\\Table\(name=['\"](\w+)['\"]", content)
    if not table_match:
        table_match = re.search(r"@ORM\\Table\(name=['\"](\w+)['\"]", content)
    table_name = table_match.group(1) if table_match else class_name.lower()
    
    # Extract columns
    columns = []
    # #[ORM\Column(type="string", length=255)]
    col_pattern = r'#\[ORM\\Column\(([^)]+)\)\]\s*(?:private|protected|public)\s+(\w+)'
    for match in re.finditer(col_pattern, content):
        attrs = match.group(1)
        col_name = match.group(2)
        # Parse type
        type_match = re.search(r'type=["\'](\w+)["\']', attrs)
        col_type = type_match.group(1) if type_match else "string"
        # Check nullable
        nullable = "nullable=true" in attrs or "nullable: true" in attrs
        # Check id
        is_id = "#[ORM\\Id]" in content[max(0, match.start()-200):match.start()]
        columns.append({
            "name": col_name,
            "type": col_type,
            "nullable": nullable,
            "is_id": is_id
        })
    
    # Extract relations
    relations = []
    # ManyToOne
    for match in re.finditer(r'#\[ORM\\ManyToOne\(([^)]*)\)\]\s*(?:private|protected|public)\s+(\w+)', content):
        attrs = match.group(1)
        field = match.group(2)
        target_match = re.search(r'targetEntity=["\'](\w+)["\']', attrs)
        target = target_match.group(1) if target_match else field
        inv_match = re.search(r'inversedBy=["\'](\w+)["\']', attrs)
        inversed = inv_match.group(1) if inv_match else None
        relations.append({
            "type": "ManyToOne",
            "field": field,
            "target": target,
            "inversed_by": inversed
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
    
    return {
        "class": class_name,
        "table": table_name,
        "columns": columns,
        "relations": relations,
        "subdomain": get_subdomain(class_name)
    }

def generate_mermaid(entities: List[Dict]) -> str:
    """Generate Mermaid ER diagram grouped by subdomain."""
    lines = ["erDiagram"]
    
    # Group by subdomain
    by_domain = {}
    for e in entities:
        domain = e["subdomain"]
        if domain not in by_domain:
            by_domain[domain] = []
        by_domain[domain].append(e)
    
    # Define entities with attributes
    for domain in sorted(by_domain.keys()):
        lines.append(f"    %% ===== {domain.upper()} =====")
        for entity in by_domain[domain]:
            cls = entity["class"]
            lines.append(f"    {cls} {{")
            for col in entity["columns"]:
                pk = " PK" if col["is_id"] else ""
                null = "" if col["nullable"] else " NOT NULL"
                lines.append(f"        {col['type']} {col['name']}{pk}{null}")
            lines.append("    }")
    
    # Define relationships
    lines.append("    %% ===== RELATIONSHIPS =====")
    for entity in entities:
        cls = entity["class"]
        for rel in entity["relations"]:
            if rel["type"] == "ManyToOne":
                lines.append(f"    {cls} }}|--|| {rel['target']} : \"{rel['field']}\"")
            elif rel["type"] == "OneToMany":
                lines.append(f"    {cls} ||--o{{ {rel['target']} : \"{rel['field']}\"")
            elif rel["type"] == "OneToOne":
                lines.append(f"    {cls} ||--|| {rel['target']} : \"{rel['field']}\"")
            elif rel["type"] == "ManyToMany":
                lines.append(f"    {cls} }}{{o--o{{ {rel['target']} : \"{rel['field']}\"")
    
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
            print(f"  ✓ {parsed['class']} ({parsed['subdomain']})")
    
    print(f"Found {len(entities)} entities")
    
    mermaid = generate_mermaid(entities)
    
    OUTPUT_FILE.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT_FILE.write_text(mermaid, encoding="utf-8")
    print(f"✅ Generated {OUTPUT_FILE}")

if __name__ == "__main__":
    main()