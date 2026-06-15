# AnswerEngineOptimization

A StaticForge feature package that optimises your site for AI-powered answer engines (LLMs, RAG pipelines, AI search). It handles structured data injection, `llms.txt` generation, AI-bot `robots.txt` rules, and data-driven FAQ schema — all automatically as part of the StaticForge build pipeline.

## Installation

```bash
composer require calevans/AnswerEngineOptimization
```

## Features

| Feature | Description |
|---|---|
| AI-bot `robots.txt` | Adds `Allow: /` rules for `OAI-SearchBot`, `ChatGPT-User`, `Google-Extended`, `Anthropic-ai`, and `Claude-Web` |
| Article JSON-LD | Injects `Article` structured data into every page `<head>` |
| BreadcrumbList JSON-LD | Injects `BreadcrumbList` structured data on all non-home pages |
| FAQ JSON-LD | Injects `FAQPage` structured data from frontmatter, `[faq]` shortcodes, or a shared JSON data file |
| `llms.txt` | Generates a site-wide `llms.txt` index for AI crawlers, with a titled header and per-page descriptions |
| `llms` link tag | Injects `<link rel="llms" href="/llms.txt">` into every page `<head>` |
| `.md` copy | Publishes a stripped Markdown copy of each page alongside its HTML output |

## Configuration

### `siteconfig.yaml`

All keys are optional. The package works with sensible defaults when they are absent.

```yaml
site:
  name: "My Site"           # Becomes the `# Title` in llms.txt and the Article schema publisher name.
                            # Defaults to "LLMs Documentation" in llms.txt when not set.
  tagline: "My tagline"     # Becomes the `> description` blockquote in llms.txt.
                            # Falls back to `site.description` if `tagline` is absent.
  description: "..."        # Fallback description for llms.txt when `site.tagline` is not set.

social:
  default_image: "https://example.com/logo.png"  # Added as `publisher.logo` in the Article JSON-LD schema.

answer_engine_optimization:
  faq_data_file: content/assets/faq.json  # Path (relative to project root) to the shared FAQ JSON data file.
                                           # Defaults to `content/assets/faq.json` when not set.
```

### Per-page frontmatter

```yaml
---
title: "Page Title"
description: "Shown as the description in the llms.txt entry for this page."
no_llms: true        # Set to true to exclude this page from llms.txt and suppress the <link rel="llms"> tag.
aeo:
  key_takeaways: "A custom summary written by the author, used verbatim in llms.txt."
  faqs:
    - question: "What is this page about?"
      answer: "It is about answer engine optimisation."
---
```

### FAQ data file

When `answer_engine_optimization.faq_data_file` is configured (or the default `content/assets/faq.json` exists), the package loads a shared bank of FAQs that can be referenced from any page via `data-faq` HTML attributes.

**`content/assets/faq.json`**

```json
[
  {"id": "what-is-aeo", "question": "What is answer engine optimisation?", "answer": "It is the practice of structuring content so AI systems can accurately surface it in answers."},
  {"id": "pricing",     "question": "How much does it cost?",              "answer": "It is free and open source."}
]
```

Reference an entry in your Markdown by adding a `data-faq` attribute to any HTML element:

```html
<div data-faq="what-is-aeo"></div>
<div data-faq="pricing"></div>
```

The matching entries are automatically included in the `FAQPage` JSON-LD schema for that page.

### Priority order for FAQ sources

FAQs from all three sources are merged in this order:

1. `aeo.faqs` in page frontmatter
2. `[faq question="…" answer="…"]` shortcodes in page content
3. `data-faq="id"` references resolved from the shared JSON data file

### Priority order for `llms.txt` summary

The per-page summary line in `llms.txt` is resolved in this order:

1. `aeo.key_takeaways` in page frontmatter (author-written, highest priority)
2. `description` in page frontmatter
3. Empty string (the colon is still emitted; the AI crawler can infer context from the `.md` copy)
