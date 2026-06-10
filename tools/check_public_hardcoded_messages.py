import re
from pathlib import Path


def iter_php_files(base: Path):
    for ext in (".php", ".blade.php"):
        yield from base.rglob(f"*{ext}")


def main() -> int:
    root = Path(__file__).resolve().parents[1]

    scan_dirs = [
        root / "app" / "Http" / "Controllers",
        root / "app" / "Http" / "Requests",
        root / "app" / "Services",
    ]

    # Heuristics: catch likely user-facing strings hardcoded in guest/panel flows.
    patterns = [
        ("with_error", re.compile(r"->with\(\s*'error'\s*,\s*'([^']{4,})'\s*\)")),
        ("with_message", re.compile(r"->with\(\s*'message'\s*,\s*'([^']{4,})'\s*\)")),
        ("with_status", re.compile(r"->with\(\s*'status'\s*,\s*'([^']{4,})'\s*\)")),
        ("abort_msg", re.compile(r"abort\(\s*(?:422|403|404|500|503)\s*,\s*'([^']{4,})'\s*\)")),
        ("validation_withmessages", re.compile(r"ValidationException::withMessages\(\s*\[[\s\S]*?\[\s*'([^']{4,})'\s*\]", re.MULTILINE)),
        ("errors_add", re.compile(r"->errors\(\)->add\(\s*'[^']+'\s*,\s*'([^']{4,})'\s*\)")),
    ]

    hits = []

    for d in scan_dirs:
        if not d.exists():
            continue
        for p in iter_php_files(d):
            # Exclude admin panel codepaths; admins use cg and are out of scope here.
            if "AdminPanel" in p.parts or "admin-panel" in str(p).lower():
                continue
            # Exclude staff/admin-only controllers & auth scaffolding (not part of guest/agency panels).
            sp = str(p).replace("/", "\\").lower()
            if "\\app\\http\\controllers\\admin\\" in sp:
                continue
            if "\\app\\http\\controllers\\auth\\" in sp:
                continue
            if p.name.lower().startswith("fakebank"):
                continue
            try:
                txt = p.read_text(encoding="utf-8", errors="ignore")
            except Exception:
                continue

            rel = str(p.relative_to(root))

            for tag, rx in patterns:
                for m in rx.finditer(txt):
                    msg = m.group(1).strip()
                    # Skip obvious internal/dev-only strings
                    if msg.lower().startswith("missing ") or msg.lower().startswith("runtime"):
                        continue
                    hits.append((rel, tag, msg))

    # De-duplicate
    uniq = []
    seen = set()
    for h in hits:
        if h in seen:
            continue
        seen.add(h)
        uniq.append(h)

    print(f"HARDCODED_MESSAGE_HITS {len(uniq)}")
    for rel, tag, msg in uniq:
        print("---")
        print(f"file: {rel}")
        print(f"type: {tag}")
        try:
            print(f"message: {msg}")
        except UnicodeEncodeError:
            # Windows console encoding fallback (emit escaped unicode)
            safe = msg.encode("ascii", errors="backslashreplace").decode("ascii", errors="ignore")
            print(f"message: {safe}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

