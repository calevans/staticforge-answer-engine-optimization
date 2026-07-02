# CLAUDE.md

## This is a distributable package, not one site's config

`calevans/answer-engine-optimization` gets dropped into many different
StaticForge sites (podcast, blog, static brochure sites, etc.) — never
hardcode one specific site's facts (a phone number, an address, a `sameAs`
link, a business name) into plugin code. All per-site facts belong in the
consuming site's `siteconfig.yaml`/frontmatter/theme, never in `src/`.

## Scope: per-page and per-build data only, not site identity

This plugin generates things that genuinely vary per page or need aggregating
across a build: Article/BreadcrumbList/FAQPage JSON-LD, `llms.txt`, the
AI-bot `robots.txt` allowlist, and the `.md` mirror. It deliberately does
**not** generate site identity schema (`Organization`/`LocalBusiness`/
`PodcastSeries`/etc.) — that data is static across every page and every
build, so it belongs as a single hand-written JSON-LD block in the
consuming site's theme, not as build-pipeline config. See README.md's
"Identity schema belongs in your theme, not here" section. (An earlier
version of this plugin did generate per-site-type identity schema via a
17-class registry mirroring the `aeo-checker` sibling project; it was
removed as disproportionate to the value — resist re-adding it.)

## Never fabricate schema data

If a field's underlying data isn't available (no title, no configured site
name, no known modification time), omit that field/block from the emitted
JSON-LD rather than filling in a placeholder or a build-time timestamp. A
missing field is honest; a fabricated one actively misleads AI crawlers and
undermines the trust signals this plugin exists to produce.
