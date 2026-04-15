# Answer Engine Optimization (AEO/AXO)

Welcome to the Answer Engine Optimization (also known as Agent Experience Optimization) feature! As Large Language Models (LLMs) and AI Agents like ChatGPT, Perplexity, and Claude become the primary way people search for information, your site needs to be "agent-ready".

This feature ensures AI crawlers can easily digest, verify, and cite your content.

## What it does

When enabled, this feature automatically:
1. **Permits AI Crawlers:** Updates your `robots.txt` to explicitly allow known AI bots (like `OAI-SearchBot` and `Claude-Web`).
2. **Generates `llms.txt`:** Creates an AI-specific sitemap at the root of your site (`/llms.txt`) that summarizes your core pages.
3. **Mirrors Raw Markdown:** Saves a clean, stripped-down `.md` version of your pages alongside the compiled HTML, providing a machine-readable format free of HTML bloat and sensitive frontmatter.
4. **Injects Structured Data:** Automatically builds and injects `application/ld+json` Schema.org data into the `<head>` of your pages, including Article and FAQ schemas.
5. **Declares Freshness:** Injects accurate `dateModified` timestamps based on file modification times, so RAG (Retrieval-Augmented Generation) models know your content is up-to-date.

---

## Configuration

You configure this feature in your `siteconfig.yaml` file. Here, you define your site's publisher defaults which are used to build the Knowledge Graph footprint.

```yaml
# Answer Engine Optimization (AXO/AEO) Configuration
answer_engine_optimization:
  enabled: true
```

## How to optimize your content

You can give AI agents direct summaries and structured FAQs using either Frontmatter or Shortcodes.

### 1. Frontmatter (The `aeo` key)

You can add an `aeo` array to your page's YAML frontmatter to provide explicit takeaways and FAQs.

```yaml
---
title: "How to Build a Birdhouse"
description: "A beginner's guide to woodworking."
aeo:
  key_takeaways: "Building a birdhouse requires pine wood, waterproof wood glue, and a 1.5-inch entry hole for small birds."
  faqs:
    - question: "What is the best wood for a birdhouse?"
      answer: "Cedar or pine are best because they are naturally weather-resistant and safe for birds."
---
```

*   **`key_takeaways`**: A concise summary fed directly into the `llms.txt` directory. If omitted, the feature will attempt to auto-extract a short summary from your content.
*   **`faqs`**: An array of questions and answers. These are silently converted into rigid `FAQPage` JSON-LD schema so AI agents treat them as verified facts.

### 2. The FAQ Shortcode

If you want your FAQs to be visible to human readers *and* AI agents simultaneously, use the `aeo_faq` shortcode directly in your Markdown body.

```markdown
[aeo_faq question="Do I need a perch on the birdhouse?"]
No, perches actually help predators access the nest. It is safer to leave the perch off.
[/aeo_faq]
```

This shortcode magically does two things:
1. It renders a cleanly formatted `<details>` and `<summary>` HTML block for your human visitors.
2. It automatically adds the question and answer to the page's invisible `FAQPage` JSON-LD schema for the AI agents.

---

## Technical Details (For Developers)

This feature is modular and hooks into the core StaticForge build pipeline via these events:
*   `PRE_RENDER`: Reads the file's modification time (mtime) and registers the `FaqShortcode`.
*   `MARKDOWN_CONVERTED`: Aggregates the summaries, explicitly parses the FAQs, and builds the schema parameter payload.
*   `POST_RENDER`: Injects the finalized JSON-LD `<script>` block into the DOM's `<head>`, and writes the sanitized `.md` file to the `public/` directory so agents can read it directly.
*   `POST_LOOP`: Compiles all accumulated page summaries and generates the final `public/llms.txt` file.
*   `ROBOTS_TXT_BUILDING`: Intercepts the robots.txt builder to inject `Allow: /` rules for standard AI crawlers without conflicting with other components.
