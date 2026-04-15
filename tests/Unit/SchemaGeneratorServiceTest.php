<?php
declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Calevans\AnswerEngineOptimization\Services\SchemaGeneratorService;

class SchemaGeneratorServiceTest extends TestCase
{
    public function testGenerate(): void
    {
        $service = new SchemaGeneratorService();
        $meta = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => 'Test "Headline"'];
        
        $result = $service->generate($meta);
        
        $this->assertStringStartsWith('<script type="application/ld+json">', $result);
        $this->assertStringEndsWith('</script>', $result);
        $this->assertStringContainsString('https:\/\/schema.org', $result);
        $this->assertStringContainsString('Test \u0022Headline\u0022', $result);
    }
}
