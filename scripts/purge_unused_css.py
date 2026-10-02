#!/usr/bin/env python3
"""
AUD2-12 CSS dead-rule purger.

Recursively walks every stylesheet (descending into @media / @supports),
removes selector blocks whose class tokens appear NOWHERE in the live
repository, and de-duplicates identical rules and @keyframes.

Safety rails:
  * Dynamic class stems built in PHP/JS (e.g. 'hs-layout-' . $layout) are
    never purged, even though their full token is not literal anywhere.
  * GeneratePress parent-theme classes (.site-content, .site-main, ...) are
    provided outside this repo and are always retained.
  * Element-only rules (no class token) are retained.

Usage:
    python3 scripts/purge_unused_css.py           # apply + rebundle
    python3 scripts/purge_unused_css.py --dry-run # report only
"""

from __future__ import annotations

import json
import os
import re
import sys

ROOT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CSS_DIR = os.path.join(ROOT_DIR, "helmetsan-theme", "assets", "css")

FILES = ["design-tokens.css", "base.css", "components.css", "mega-menu.css", "pages.css"]

# NOTE: do NOT skip "assets" globally — helmetsan-theme/assets/js is a live
# class-token source. .css files are excluded via SKIP_EXT so the stylesheets
# cannot keep their own rules "alive".
SKIP_DIRS = {".git", "vendor", "node_modules", "nightmodeaudit", ".phpunit.cache"}
SKIP_EXT = {
    ".css", ".png", ".webp", ".jpg", ".jpeg", ".avif", ".mo", ".ico",
    ".gz", ".zip", ".db", ".sqlite", ".woff", ".woff2", ".ttf",
}

# Class stems concatenated at runtime in PHP — the full token never appears
# literally, so a literal corpus match would wrongly flag these as dead.
DYNAMIC_STEMS = (
    "hs-layout-",   # inc/hooks.php  $classes[] = 'hs-layout-' . $layout
    "hs-helm-",     # template concat
    "hs-status--",  # template concat
)

# WordPress core emits these on <body> at runtime from taxonomy slugs /
# query state — the literal token never exists in any repo file.
WP_RUNTIME_PREFIXES = (
    "tax-", "is-", "page-id-", "postid-", "author-", "date-", "wp-", "sf-",
)

# Classes supplied by the GeneratePress parent theme / WordPress core — they
# exist only in wp-content/themes/generatepress, which is outside this repo.
PARENT_THEME_TOKENS = {
    "site-content", "content-area", "site-main", "inside-article",
    "entry-title", "entry-content", "site-header", "site-footer",
    "main-navigation", "menu-toggle", "site-info", "content-sidebar",
    "widget", "site-branding", "page-header", "taxonomy-description",
    "nav-links", "post-navigation", "comments-area", "comment-respond",
    "wp-block-*",
}


def build_corpus() -> str:
    chunks: list[str] = []
    for root, dirs, names in os.walk(ROOT_DIR):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for name in names:
            if os.path.splitext(name)[1].lower() in SKIP_EXT:
                continue
            path = os.path.join(root, name)
            if path == os.path.join(CSS_DIR, "helmetsan-bundle.min.css"):
                continue
            try:
                chunks.append(open(path, encoding="utf-8", errors="ignore").read())
            except OSError:
                continue
    return "".join(chunks)


def is_live(token: str, corpus: str) -> bool:
    if token in PARENT_THEME_TOKENS:
        return True
    if any(token.startswith(stem) for stem in DYNAMIC_STEMS):
        return True
    if any(token.startswith(pfx) for pfx in WP_RUNTIME_PREFIXES):
        return True
    return token in corpus


def strip_comments(css: str) -> str:
    return re.sub(r"/\*.*?\*/", "", css, flags=re.DOTALL)


def split_blocks(css: str) -> list[tuple[str, str, str]]:
    """Return (raw_head, prelude, block) for each top-level statement.

    `block` is the *complete* statement from the previous `}` (or file start)
    through its closing `}`, so it already carries its leading whitespace and
    any section comments. Reconstruct a kept block from `block[:open+1] ...`
    rather than re-adding `raw_head`, otherwise whitespace is duplicated.

    `prelude` is comment-stripped so `/* c */ @media` classifies as @media.
    A brace-less tail is returned as (raw_tail, "", "").
    """
    out: list[tuple[str, str, str]] = []
    i, n, start = 0, len(css), 0
    while i < n:
        if css[i] == "{":
            head = css[start:i]
            j, depth = i + 1, 1
            while j < n and depth > 0:
                if css[j] == "{":
                    depth += 1
                elif css[j] == "}":
                    depth -= 1
                j += 1
            out.append((head, strip_comments(head).strip(), css[start:j]))
            start = j
            i = j
            continue
        i += 1
    tail = css[start:]
    if tail:
        # Keep even a whitespace-only tail: it is the newline separating the
        # last statement from EOF or a parent @media's closing brace.
        out.append((tail, "", ""))
    return out


def minify(css: str) -> str:
    css = strip_comments(css)
    css = re.sub(r"\s+", " ", css)
    css = re.sub(r"\s*([:;{}])\s*", r"\1", css)
    css = re.sub(r";}", "}", css)
    return css.strip()


def purge_segments(segments: list[tuple[str, str, str]],
                   corpus: str, stats: dict, log: list) -> str:
    """Filter one nesting level; returns the concatenated kept statements.

    Kept blocks are re-emitted verbatim (`block`, which already carries its own
    leading whitespace and section comments). A nested @media/@supports is
    rebuilt as `block[:open+1] + <purged inner> + "}"` so the original head —
    comments, spacing and all — is preserved.
    """
    kept: list[str] = []
    for head, prelude, block in segments:
        if not block:
            kept.append(head)
            continue

        inner_open = block.index("{")
        inner = block[inner_open + 1: -1]

        if prelude.startswith("@"):
            at_rule = prelude.split()[0].lstrip("@").split("(")[0]
            if at_rule == "keyframes":
                name = prelude.split()[1]
                # Registry is shared across files: only an *identical* body is
                # a safe removal. A same-name/different-body pair changes the
                # cascade (last definition wins) so it is reported, not cut.
                norm_body = re.sub(r"\s+", "", inner)
                prev = stats["seen_keyframes"].get(name)
                if prev is not None and prev == norm_body:
                    stats["bytes"] += len(block)
                    stats["blocks"] += 1
                    log.append((f"@keyframes {name} (dup)", len(block)))
                    continue
                if prev is None:
                    stats["seen_keyframes"][name] = norm_body
                else:
                    stats["warnings"].append(
                        f"@keyframes {name} defined twice with different "
                        f"bodies — kept both (last one wins)")
                kept.append(block)
                continue
            if at_rule in {"media", "supports", "container"}:
                new_inner = purge_segments(
                    split_blocks(inner), corpus, stats, log)
                if new_inner != inner:
                    block = block[:inner_open + 1] + new_inner + "}"
                kept.append(block)
                continue
            kept.append(block)
            continue

        tokens = re.findall(r"\.([A-Za-z0-9_-]+)", prelude)
        if tokens and all(not is_live(t, corpus) for t in tokens):
            stats["bytes"] += len(block)
            stats["blocks"] += 1
            log.append((prelude.replace("\n", " ")[:90], len(block)))
            continue

        fingerprint = prelude + "|" + minify(inner)
        if fingerprint in stats["seen_rules"]:
            stats["bytes"] += len(block)
            stats["blocks"] += 1
            log.append((f"{prelude[:60]} (dup rule)", len(block)))
            continue
        stats["seen_rules"].add(fingerprint)
        kept.append(block)

    return "".join(kept)


def purge_file(path: str, corpus: str, dry: bool,
               kf_registry: dict, warnings: list[str]) -> tuple[dict, list]:
    css = open(path, encoding="utf-8").read()
    stats = {"bytes": 0, "blocks": 0, "seen_rules": set(),
             "seen_keyframes": kf_registry, "warnings": warnings}
    log: list[tuple[str, int]] = []
    segments = split_blocks(css)
    out = purge_segments(segments, corpus, stats, log)

    if not dry and log:
        open(path, "w", encoding="utf-8").write(out)
    return stats, log


def main() -> int:
    dry = "--dry-run" in sys.argv
    corpus = build_corpus()
    total_bytes = 0
    total_blocks = 0
    details: dict[str, list] = {}
    kf_registry: dict[str, str] = {}
    warnings: list[str] = []

    for name in FILES:
        path = os.path.join(CSS_DIR, name)
        if not os.path.isfile(path):
            continue
        stats, log = purge_file(path, corpus, dry, kf_registry, warnings)
        total_bytes += stats["bytes"]
        total_blocks += stats["blocks"]
        details[name] = log

    print(json.dumps({
        "dry_run": dry,
        "removed_blocks": total_blocks,
        "removed_raw_bytes": total_bytes,
        "removed_kb": round(total_bytes / 1024, 1),
        "keyframe_warnings": warnings,
    }, indent=2))

    for name, entries in details.items():
        print(f"\n== {name} ({len(entries)} blocks) ==")
        for prelude, size in sorted(entries, key=lambda e: -e[1])[:15]:
            print(f"  {size:>5}  {prelude}")

    if not dry:
        sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
        from bundle_and_minify_css import build_bundle
        build_bundle()
    return 0


if __name__ == "__main__":
    sys.exit(main())
