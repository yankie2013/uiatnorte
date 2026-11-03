#!/usr/bin/env python3
"""Verify source provenance without loading the application or accessing any database."""
import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
manifest = json.loads((root / "release/v6-manifest.json").read_text())
overrides = manifest.get("test_only_overrides", {})
failures = []
for name, original in manifest["files"].items():
    path = root / name
    expected = overrides.get(name, original)
    if not path.is_file() or hashlib.sha256(path.read_bytes()).hexdigest() != expected:
        failures.append(name)
installer = root / "release/corregir-necropsia-fiel-uiat-v6.py"
if hashlib.sha256(installer.read_bytes()).hexdigest() != manifest["installer_sha256"]:
    failures.append(installer.name)
if failures:
    raise SystemExit("Integridad incorrecta: " + ", ".join(failures))
print(f"OK: {len(manifest['files'])} archivos v6; {len(overrides)} ajustes exclusivos de pruebas; instalador íntegro.")
