# AEO Feature Ideas

## ImageObject Schema

Auto-generate `ImageObject` JSON-LD for images found in rendered page HTML.

**Why:** AI engines can extract images as structured media when marked up as `ImageObject`. Sites with large photo galleries (e.g., real estate listings) get significantly more structured data coverage.

**Approach:** In `onPostRender`, parse `<img>` tags from `$parameters['rendered_content']`. For each image with a `src` and `alt`, emit an `ImageObject` with `url`, `description`, and optionally `width`/`height` from the `width`/`height` attributes. Could be gated by a `siteconfig.yaml` flag since it can be verbose on photo-heavy pages.

---

## Offer Schema

Emit an `Offer` JSON-LD node for pages that declare pricing in frontmatter.

**Why:** Price, currency, and availability are core structured data for any listing or product page. AI engines can surface this directly in answers.

**Approach:** Read optional frontmatter keys (`offer_price`, `offer_currency`, `offer_availability`) during `onMarkdownConverted` or `onPostRender`. If present, generate an `Offer` node and inject it. Keeps the feature opt-in so non-commerce sites are unaffected.
