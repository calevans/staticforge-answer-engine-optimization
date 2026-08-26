<?php

declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Services;

use EICC\Utils\Log;

/**
 * Loads and indexes a JSON FAQ data file, then resolves data-faq attribute
 * references found in rendered HTML into structured question/answer pairs
 * for FAQPage schema generation.
 *
 * Expected JSON format:
 * [
 *   {"id": "some-id", "question": "Question text?", "answer": "Answer text."},
 *   ...
 * ]
 *
 * Configuration (siteconfig.yaml):
 *   answer_engine_optimization:
 *     faq_data_file: content/assets/faq.json   # optional, this is the default
 */
class FaqDataService
{
    private ?Log $logger;

    /** @var array<string, array{question: string, answer: string}>|null */
    private ?array $index = null;

    public function __construct(?Log $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Load and index the FAQ data file. Subsequent calls return the cached index.
     *
     * @param string $appRoot Absolute path to the project root
     * @param string|null $configuredPath Path from siteconfig, relative to appRoot
     * @return array<string, array{question: string, answer: string}>
     */
    public function load(string $appRoot, ?string $configuredPath = null): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $appRoot = rtrim($appRoot, '/');
        $candidates = array_filter([
            $configuredPath ? $appRoot . '/' . ltrim($configuredPath, '/') : null,
            $appRoot . '/content/assets/faq.json',
        ]);

        foreach ($candidates as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $raw = json_decode((string) file_get_contents($path), true);
            if (!is_array($raw)) {
                $this->logger?->log('WARNING', "FaqDataService: could not parse JSON from {$path}");
                continue;
            }

            $this->index = [];
            foreach ($raw as $item) {
                if (isset($item['id'], $item['question'], $item['answer'])) {
                    $this->index[(string) $item['id']] = [
                        'question' => (string) $item['question'],
                        'answer'   => (string) $item['answer'],
                    ];
                }
            }

            $this->logger?->log('INFO', "FaqDataService: loaded " . count($this->index) . " entries from {$path}");
            return $this->index;
        }

        $this->index = [];
        return $this->index;
    }

    /**
     * Scan HTML for data-faq="id" attributes and return matched FAQ entries
     * in the order they appear, deduplicated.
     *
     * @param string $html Rendered HTML content
     * @return array<int, array{question: string, answer: string}>
     */
    public function resolve(string $html): array
    {
        if (empty($this->index)) {
            return [];
        }

        preg_match_all('/data-faq="([^"]+)"/', $html, $matches);
        $ids = array_unique($matches[1] ?? []);

        $faqs = [];
        foreach ($ids as $id) {
            if (isset($this->index[$id])) {
                $faqs[] = $this->index[$id];
            } else {
                $this->logger?->log('WARNING', "FaqDataService: no entry for data-faq=\"{$id}\"");
            }
        }

        return $faqs;
    }
}
