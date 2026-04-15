<?php

declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Services;

class LlmsTxtGeneratorService
{
    private $logger;
    private array $pages = [];

    public function __construct($logger = null)
    {
        $this->logger = $logger;
    }

    public function addPage(string $url, string $title, string $aiSummary): void
    {
        $this->pages[] = [
            'url' => $url,
            'title' => $title,
            'summary' => $aiSummary
        ];
    }

    public function hasPages(): bool
    {
        return !empty($this->pages);
    }

    public function generate(string $outputDir): void
    {
        if (empty($outputDir)) {
            throw new \InvalidArgumentException("Output directory cannot be empty.");
        }

        $content = "# LLMs Documentation\n\n";
        foreach ($this->pages as $page) {
            $content .= "- [{$page['title']}]({$page['url']}): {$page['summary']}\n";
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if (!is_writable($outputDir)) {
            if ($this->logger) {
                $this->logger->log('ERROR', "Cannot write to {$outputDir}. Permission denied.");
            }
            throw new \RuntimeException("Cannot write to output directory: {$outputDir}");
        }

        $dest = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . 'llms.txt';
        file_put_contents($dest, $content);

        if ($this->logger) {
            $this->logger->log('INFO', "Generated llms.txt with " . count($this->pages) . " entries.");
        }
    }
}
