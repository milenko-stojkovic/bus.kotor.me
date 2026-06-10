import re
from pathlib import Path


def iter_php_like_files(base: Path):
    for ext in (".php", ".blade.php"):
        yield from base.rglob(f"*{ext}")


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    seeder = root / "database" / "seeders" / "UiTranslationsSeeder.php"

    # Limit scan to guest + agency panel UI (exclude admin-panel).
    scan_dirs = [
        # Blade views (guest + panel + payment)
        root / "resources" / "views" / "panel",
        root / "resources" / "views" / "guest",
        root / "resources" / "views" / "payment",
        root / "resources" / "views" / "components",
        root / "resources" / "views" / "partials",
        # PHP sources that can emit user-facing strings for guest/panel flows
        root / "app" / "Http" / "Controllers",
        root / "app" / "Http" / "Requests",
        root / "app" / "Services",
        root / "app" / "Support",
    ]

    # 1) $pn('key', 'fallback') → group=panel
    pn_re = re.compile(r"\$pn\(\s*'([^']+)'\s*,\s*'([^']*)'\s*\)")

    # 2) UiText::t('group','key','fallback'...) where fallback is a literal string
    # Supports: UiText::t, \App\Support\UiText::t, App\Support\UiText::t
    uitext_fallback_re = re.compile(
        r"(?:\\?App\\Support\\)?UiText::t\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']*)'"
    )

    used: dict[tuple[str, str], set[str]] = {}  # (group,key) -> files

    for d in scan_dirs:
        if not d.exists():
            continue
        for p in iter_php_like_files(d):
            # Exclude admin panel codepaths; admins use cg and are out of scope here.
            if "AdminPanel" in p.parts or "admin-panel" in str(p).lower():
                continue
            try:
                txt = p.read_text(encoding="utf-8", errors="ignore")
            except Exception:
                continue

            rel = str(p.relative_to(root))

            for m in pn_re.finditer(txt):
                key = m.group(1)
                used.setdefault(("panel", key), set()).add(rel)

            for m in uitext_fallback_re.finditer(txt):
                group = m.group(1)
                key = m.group(2)
                used.setdefault((group, key), set()).add(rel)

    # Parse seeded translations (all groups) from UiTranslationsSeeder.php
    seed_txt = seeder.read_text(encoding="utf-8", errors="ignore")
    seed_re = re.compile(
        r"\['group'\s*=>\s*'([^']+)'\s*,\s*'key'\s*=>\s*'([^']+)'\s*,\s*'locale'\s*=>\s*'([^']+)'\s*,"
    )
    seeded: dict[tuple[str, str], set[str]] = {}
    for g, k, loc in seed_re.findall(seed_txt):
        seeded.setdefault((g, k), set()).add(loc)

    # For public/agency panels we care about both cg and en because users can pick language.
    missing: list[tuple[str, str, list[str], list[str]]] = []
    for (g, k), files in sorted(used.items()):
        locs = seeded.get((g, k), set())
        miss = [l for l in ("cg", "en") if l not in locs]
        if miss:
            missing.append((g, k, miss, sorted(files)))

    print(f"TOTAL_KEYS_WITH_LITERAL_FALLBACKS {len(used)}")
    print(f"MISSING_TRANSLATIONS_IN_SEEDER {len(missing)}")
    for g, k, miss, files in missing:
        print("---")
        print(f"group: {g}")
        print(f"key: {k}")
        print(f"missing_locales: {','.join(miss)}")
        print("files:")
        for f in files:
            print(f"  {f}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

