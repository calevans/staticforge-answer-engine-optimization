<?php

declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Services;

use EICC\Utils\Log;

class LlmsTxtGeneratorService
{
    private ?Log $logger;
    private array $pages = [];

    public function __construct(?Log $logger = null)
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

    public function generate(string $outputDir, string $siteName = '', string $siteDescription = ''): void
    {
        if (empty($outputDir)) {
            throw new \InvalidArgumentException("Output directory cannot be empty.");
        }

        $title = $siteName !== '' ? $siteName : 'LLMs Documentation';
        $content = "# {$title}\n\n";

        if ($siteDescription !== '') {
            $content .= "> {$siteDescription}\n\n";
        }

        foreach ($this->pages as $page) {
            $sectionName = strstr($page['title'], ' | ', true) ?: $page['title'];
            $content .= "## {$sectionName}\n\n";
            $content .= "- [{$page['title']}]({$page['url']})";
            if ($page['summary'] !== '') {
                $content .= ": {$page['summary']}";
            }
            $content .= "\n\n";
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
