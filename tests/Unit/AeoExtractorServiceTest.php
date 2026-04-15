<?php
declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Calevans\AnswerEngineOptimization\Services\AeoExtractorService;

class AeoExtractorServiceTest extends TestCase
{
    public function testExtractSummary(): void
    {
        $service = new AeoExtractorService();
        $content = "<html><body><h1>Hello World</h1><p>This is a test summary that should be extracted without the HTML tags.</p></body></html>";
        
        $summary = $service->extractSummary($content);
        
        $this->assertEquals("Hello WorldThis is a test summary that should be extracted without the HTML tags.", $summary);
    }
}
