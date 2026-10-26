#!/usr/bin/env python3
"""Check a site folder against STANDARDS.md. Usage: python3 tools/check_site.py public_html
Exit code 1 if any Block rule fails. The portal will run this on every upload."""
import json, os, re, sys
from html.parser import HTMLParser
from urllib.parse import urlparse

class Page(HTMLParser):
    def __init__(self):
        super().__init__()
        self.tags, self.title, self._in_title, self.h, self.imgs, self.links, self.ld, self._in_ld = [], "", False, [], [], [], [], False
        self.meta, self.canonical, self.lang, self.landmarks = {}, None, None, set()
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "html": self.lang = a.get("lang")
        if tag == "title": self._in_title = True
        if re.fullmatch(r"h[1-6]", tag): self.h.append(int(tag[1]))
        if tag == "img": self.imgs.append(a)
        if tag == "a" and a.get("href"): self.links.append(a["href"])
        if tag == "meta": self.meta[a.get("name") or a.get("property") or a.get("charset") and "charset"] = a.get("content", a.get("charset"))
        if tag == "link" and a.get("rel") == "canonical": self.canonical = a.get("href")
        if tag == "script" and a.get("type") == "application/ld+json": self._in_ld = True; self.ld.append("")
        if tag in ("header", "nav", "main", "footer"): self.landmarks.add(tag)
    def handle_endtag(self, tag):
        if tag == "title": self._in_title = False
        if tag == "script": self._in_ld = False
    def handle_data(self, d):
        if self._in_title: self.title += d
        if self._in_ld: self.ld[-1] += d

def check(root):
    results = []  # (level, file, message)
    add = lambda lvl, f, m: results.append((lvl, f, m))
    pages = []
    for dp, _, fs in os.walk(root):
        for f in fs:
            if f.endswith(".html"): pages.append(os.path.join(dp, f))
    titles, descs = {}, {}
    for p in sorted(pages):
        rel = os.path.relpath(p, root)
        raw = open(p, encoding="utf-8", errors="replace").read()
        pg = Page(); pg.feed(raw)
        noindex = "noindex" in (pg.meta.get("robots") or "")
        if not raw.lstrip().lower().startswith("<!doctype html>"): add("BLOCK", rel, "missing <!doctype html>")
        if not pg.lang: add("BLOCK", rel, "missing <html lang>")
        if "charset" not in pg.meta: add("BLOCK", rel, "missing <meta charset>")
        if "width=device-width" not in (pg.meta.get("viewport") or ""): add("BLOCK", rel, "missing viewport meta")
        t = pg.title.strip()
        if not (30 <= len(t) <= 60) and not noindex: add("BLOCK", rel, f"title is {len(t)} chars (want 30-60)")
        titles.setdefault(t, []).append(rel)
        d = pg.meta.get("description") or ""
        if not (70 <= len(d) <= 160) and not noindex: add("WARN", rel, f"meta description is {len(d)} chars (want 70-160)")
        descs.setdefault(d, []).append(rel)
        if pg.h.count(1) != 1: add("BLOCK", rel, f"{pg.h.count(1)} <h1> tags (want exactly 1)")
        for a, b in zip(pg.h, pg.h[1:]):
            if b > a + 1: add("BLOCK", rel, f"heading jumps from h{a} to h{b}"); break
        if not pg.canonical: add("WARN", rel, "missing canonical link")
        for img in pg.imgs:
            if "alt" not in img: add("BLOCK", rel, f"img without alt: {img.get('src')}")
            if not (img.get("width") and img.get("height")): add("WARN", rel, f"img without width/height: {img.get('src')}")
        for k in ("og:title", "og:description", "og:url"):
            if k not in pg.meta: add("WARN", rel, f"missing {k}")
        if not {"main"} <= pg.landmarks: add("WARN", rel, "missing <main>")
        for blob in pg.ld:
            try: json.loads(blob)
            except ValueError: add("BLOCK", rel, "invalid JSON-LD")
        if re.search(r"wp-content|wp-includes|elementor|data-vc-", raw): add("BLOCK", rel, "WordPress or page-builder leftovers")
        for href in pg.links:
            u = urlparse(href)
            if u.scheme or u.netloc or href.startswith(("#", "mailto:", "tel:")): continue
            path = u.path
            target = os.path.join(root, path.lstrip("/")) if path.startswith("/") else os.path.join(os.path.dirname(p), path)
            if path.endswith("/") or os.path.isdir(target): target = os.path.join(target, "index.html")
            if not os.path.exists(target) and not path.endswith(".php"): add("BLOCK", rel, f"broken link: {href}")
        size = os.path.getsize(p)
        if size > 1_500_000: add("WARN", rel, f"page is {size//1000} KB")
    for t, fs in titles.items():
        if len(fs) > 1: add("BLOCK", ", ".join(fs), f"duplicate title: {t}")
    for d, fs in descs.items():
        if d and len(fs) > 1: add("WARN", ", ".join(fs), "duplicate meta description")
    robots = os.path.join(root, "robots.txt")
    if not os.path.exists(robots) or "Sitemap:" not in open(robots).read(): add("BLOCK", "robots.txt", "missing or has no Sitemap: line")
    if not os.path.exists(os.path.join(root, "sitemap.xml")): add("BLOCK", "sitemap.xml", "missing")
    if not os.path.exists(os.path.join(root, "404.html")): add("WARN", "404.html", "missing custom 404 page")
    for dp, _, fs in os.walk(root):
        for f in fs:
            if f.lower().endswith((".jpg", ".jpeg", ".png", ".webp", ".gif")) and os.path.getsize(os.path.join(dp, f)) > 300_000:
                add("WARN", os.path.relpath(os.path.join(dp, f), root), "image over 300 KB")
    return results, len(pages)

if __name__ == "__main__":
    res, n = check(sys.argv[1] if len(sys.argv) > 1 else "public_html")
    for lvl, f, m in res: print(f"{lvl:5}  {f}: {m}")
    blocks = sum(1 for r in res if r[0] == "BLOCK")
    print(f"\n{n} pages checked: {blocks} blocking, {len(res) - blocks} warnings")
    sys.exit(1 if blocks else 0)
