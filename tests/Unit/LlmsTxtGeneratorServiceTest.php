<?php
declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Calevans\AnswerEngineOptimization\Services\LlmsTxtGeneratorService;

class LlmsTxtGeneratorServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/staticforge_test_' . uniqid();
        mkdir($this->tempDir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir . '/public/llms.txt')) {
            unlink($this->tempDir . '/public/llms.txt');
        }
        rmdir($this->tempDir . '/public');
        rmdir($this->tempDir);
    }

    public function testGenerate(): void
    {
        $service = new LlmsTxtGeneratorService();
        $service->addPage('/about', 'About Us', 'This is the about page summary.');
        $service->addPage('/contact', 'Contact Us', 'This is the contact page summary.');
        
        $outputDir = $this->tempDir . '/public';
        $service->generate($outputDir);
        
        $this->assertFileExists($outputDir . '/llms.txt');
        
        $content = file_get_contents($outputDir . '/llms.txt');
        $this->assertStringContainsString('# LLMs Documentation', $content);
        $this->assertStringContainsString('- [About Us](/about): This is the about page summary.', $content);
        $this->assertStringContainsString('- [Contact Us](/contact): This is the contact page summary.', $content);
    }
}
