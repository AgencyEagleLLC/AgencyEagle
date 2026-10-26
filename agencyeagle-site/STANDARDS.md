# Agency Eagle Site Standards (v0.1 draft)

Every Agency Eagle site, whether we build it or a client's AI does, must meet these before it goes live. The portal checks uploads against this list. Items marked **Block** stop a deploy; **Warn** items show on the fix list.

Draft source: Agency Eagle's own practice. To be merged with the platform standards doc once it is connected.

## Every page
| Rule | Level |
|---|---|
| `<!doctype html>`, `<html lang="en">`, `<meta charset="utf-8">` | Block |
| `<meta name="viewport" content="width=device-width, initial-scale=1">` | Block |
| Unique `<title>`, 30 to 60 characters, includes the business or page topic | Block |
| Unique meta description, 70 to 160 characters | Warn |
| Exactly one `<h1>`; headings do not skip levels | Block |
| `<link rel="canonical">` with the full https URL | Warn |
| Every `<img>` has `alt` (empty `alt=""` only for decoration) and width/height | Block |
| Open Graph title, description, url | Warn |
| No broken internal links | Block |
| Page weight under 1.5 MB; images under 300 KB each, WebP or optimized JPEG/PNG | Warn |
| Landmarks: `header`, `nav`, `main`, `footer`; a skip link | Warn |
| No inline `<script>` from unknown third parties; no WordPress or page-builder leftovers | Block |

## Every site
| Rule | Level |
|---|---|
| `robots.txt` with a `Sitemap:` line | Block |
| `sitemap.xml` listing every public page | Block |
| JSON-LD schema for the business type (e.g. `LocalBusiness`, `Physician`, `ProfessionalService`) with name, url, phone, address when public | Warn |
| Same name, address and phone everywhere on the site | Warn |
| HTTPS only; `www` redirects to the bare domain (or the reverse, one way only) | Block |
| A custom 404 page | Warn |
| Forms do not collect health, payment or ID numbers | Block |
| Plain HTML, CSS and small vanilla JS. No build step needed to view the site | Block |

## File layout
```
public_html/
  index.html
  <page>/index.html      # clean URLs: /services/ not /services.html
  assets/                # css, js, images, fonts
  robots.txt
  sitemap.xml
  404.html
```
