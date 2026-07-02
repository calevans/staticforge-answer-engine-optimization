<?php
declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Calevans\AnswerEngineOptimization\Services\AeoExtractorService;

class AeoExtractorServiceTest extends TestCase
{
    public function testExtractSummaryPrefersFirstParagraphOverHeading(): void
    {
        $service = new AeoExtractorService();
        $content = '<h1>Hello World</h1><p>This is a test summary that should be extracted without the HTML tags.</p>';

        $summary = $service->extractSummary($content);

        $this->assertSame('This is a test summary that should be extracted without the HTML tags.', $summary);
    }

    public function testExtractSummaryReturnsEmptyStringForEmptyContent(): void
    {
        $service = new AeoExtractorService();
        $this->assertSame('', $service->extractSummary('   '));
    }

    public function testExtractSummaryFallsBackToFullTextWithoutParagraph(): void
    {
        $service = new AeoExtractorService();
        $summary = $service->extractSummary('<h1>Hello World</h1><ul><li>One</li><li>Two</li></ul>');

        $this->assertSame('Hello World One Two', $summary);
    }

    public function testExtractSummaryStripsScriptAndStyleContent(): void
    {
        $service = new AeoExtractorService();
        $summary = $service->extractSummary('<p>Visible text.</p><script>var x = 1;</script><style>.a{color:red}</style>');

        $this->assertSame('Visible text.', $summary);
    }

    public function testExtractSummaryTruncatesAtWordBoundary(): void
    {
        $service = new AeoExtractorService();
        $longText = str_repeat('word ', 100);
        $summary = $service->extractSummary("<p>{$longText}</p>", 20);

        $this->assertLessThanOrEqual(21, mb_strlen($summary));
        $this->assertStringEndsWith('…', $summary);
        $this->assertStringNotContainsString('  ', $summary);
    }
}
