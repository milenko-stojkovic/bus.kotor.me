import re
from pathlib import Path


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    views_dir = root / "resources" / "views"
    seeder = root / "database" / "seeders" / "UiTranslationsSeeder.php"

    # Collect keys used with $pn('key', 'fallback') in blade files
    pn_re = re.compile(r"\$pn\(\s*'([^']+)'\s*,")

    used: dict[str, set[str]] = {}
    for p in views_dir.rglob("*.blade.php"):
        try:
            txt = p.read_text(encoding="utf-8", errors="ignore")
        except Exception:
            continue
        for m in pn_re.finditer(txt):
            k = m.group(1)
            used.setdefault(k, set()).add(str(p.relative_to(root)))

    seed_txt = seeder.read_text(encoding="utf-8", errors="ignore")
    seed_re = re.compile(
        r"\['group'\s*=>\s*'([^']+)'\s*,\s*'key'\s*=>\s*'([^']+)'\s*,\s*'locale'\s*=>\s*'([^']+)'\s*,"
    )
    seeded: dict[tuple[str, str], set[str]] = {}
    for g, k, loc in seed_re.findall(seed_txt):
        seeded.setdefault((g, k), set()).add(loc)

    missing: list[tuple[str, list[str], list[str]]] = []
    for k, files in sorted(used.items()):
        locs = seeded.get(("panel", k), set())
        if not locs:
            missing.append((k, ["cg", "en"], sorted(files)))
        else:
            miss = [l for l in ("cg", "en") if l not in locs]
            if miss:
                missing.append((k, miss, sorted(files)))

    print(f"TOTAL_USED_PN_KEYS {len(used)}")
    print(f"MISSING_PANEL_TRANSLATIONS {len(missing)}")
    for k, miss, files in missing:
        print("---")
        print(f"key: {k}")
        print(f"missing_locales: {','.join(miss)}")
        print("files:")
        for f in files:
            print(f"  {f}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

