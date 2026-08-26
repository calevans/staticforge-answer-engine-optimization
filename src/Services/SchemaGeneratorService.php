<?php

declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Services;

use EICC\Utils\Log;

class SchemaGeneratorService
{
    private ?Log $logger;

    public function __construct(?Log $logger = null)
    {
        $this->logger = $logger;
    }

    public function generate(array $meta): string
    {
        // Generates JSON-LD output
        return '<script type="application/ld+json">' . json_encode($meta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . '</script>';
    }
}
