<?php

declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Services;

use EICC\Utils\Log;

class AeoExtractorService
{
    private ?Log $logger;

    public function __construct(?Log $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Extracts a short plain-text summary from rendered page HTML: the first
     * paragraph's text (falling back to the full text content if there is no
     * `<p>`), whitespace-collapsed and truncated at a word boundary. Used as
     * the llms.txt per-page summary when no author-written `key_takeaways` or
     * `description` is available, so it must never dump raw, untruncated,
     * word-concatenated page text (a stub that did so previously produced
     * garbage summaries and bloated llms.txt files).
     */
    public function extractSummary(string $content, int $maxLength = 300): string
    {
        if (trim($content) === '') {
            return '';
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $content . '</div>');
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        foreach (iterator_to_array($xpath->query('//script | //style') ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }

        // DOMDocument::textContent concatenates adjacent block elements with no
        // separator (e.g. "<h1>Hello</h1><p>World</p>" -> "HelloWorld"); insert
        // a space after each block element so extracted text stays word-separated.
        foreach (['p', 'div', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'br', 'tr', 'td'] as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $element) {
                $element->parentNode?->insertBefore($dom->createTextNode(' '), $element->nextSibling);
            }
        }

        $paragraph = $xpath->query('//p')?->item(0);
        $text = $paragraph !== null ? $paragraph->textContent : $dom->textContent;

        $text = trim((string) preg_replace('/\s+/', ' ', (string) $text));
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, '.,;:') . '…';
    }
}
