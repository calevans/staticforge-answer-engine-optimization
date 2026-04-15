<?php

declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Services;

class AeoExtractorService
{
    private $logger;

    public function __construct($logger = null)
    {
        $this->logger = $logger;
    }

    public function extractSummary(string $content): string
    {
        // Simple DOM extractor stub
        return strip_tags($content);
    }
}
