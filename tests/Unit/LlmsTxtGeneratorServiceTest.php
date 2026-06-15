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
        $this->assertStringContainsString("## About Us\n\n[About Us](/about)\n\nThis is the about page summary.", $content);
        $this->assertStringContainsString("## Contact Us\n\n[Contact Us](/contact)\n\nThis is the contact page summary.", $content);
    }

    public function testGeneratePopulatesDescriptionFromFrontmatter(): void
    {
        $service = new LlmsTxtGeneratorService();
        $service->addPage(
            'https://example.com/page.md',
            'Page Title',
            'The page description from frontmatter.'
        );

        $outputDir = $this->tempDir . '/public';
        $service->generate($outputDir);

        $content = file_get_contents($outputDir . '/llms.txt');
        $this->assertStringContainsString("## Page Title\n\n[Page Title](https://example.com/page.md)\n\nThe page description from frontmatter.", $content);
    }

    public function testGenerateWithEmptyDescriptionOmitsSummaryLine(): void
    {
        $service = new LlmsTxtGeneratorService();
        $service->addPage('https://example.com/page.md', 'Page Title', '');

        $outputDir = $this->tempDir . '/public';
        $service->generate($outputDir);

        $content = file_get_contents($outputDir . '/llms.txt');
        $this->assertStringContainsString("## Page Title\n\n[Page Title](https://example.com/page.md)\n\n", $content);
        $this->assertStringNotContainsString('[Page Title](https://example.com/page.md): ', $content);
    }

    public function testGenerateUsesSiteNameAsTitle(): void
    {
        $service = new LlmsTxtGeneratorService();
        $service->addPage('/about', 'About Us', 'About summary.');

        $outputDir = $this->tempDir . '/public';
        $service->generate($outputDir, 'Acme Corp');

        $content = file_get_contents($outputDir . '/llms.txt');
        $this->assertStringContainsString('# Acme Corp', $content);
        $this->assertStringNotContainsString('# LLMs Documentation', $content);
    }

    public function testGenerateDefaultsTitleWhenSiteNameEmpty(): void
    {
        $service = new LlmsTxtGeneratorService();
        $service->addPage('/about', 'About Us', 'About summary.');

        $outputDir = $this->tempDir . '/public';
        $service->generate($outputDir, '');

        $content = file_get_contents($outputDir . '/llms.txt');
        $this->assertStringContainsString('# LLMs Documentation', $content);
    }

    public function testGenerateIncludesSiteDescriptionBlockquote(): void
    {
        $service = new LlmsTxtGeneratorService();
        $service->addPage('/about', 'About Us', 'About summary.');

        $outputDir = $this->tempDir . '/public';
        $service->generate($outputDir, 'Acme Corp', 'We build great things.');

        $content = file_get_contents($outputDir . '/llms.txt');
        $this->assertStringContainsString('> We build great things.', $content);
    }

    public function testGenerateOmitsBlockquoteWhenDescriptionEmpty(): void
    {
        $service = new LlmsTxtGeneratorService();
        $service->addPage('/about', 'About Us', 'About summary.');

        $outputDir = $this->tempDir . '/public';
        $service->generate($outputDir, 'Acme Corp', '');

        $content = file_get_contents($outputDir . '/llms.txt');
        $this->assertStringNotContainsString('>', $content);
    }

    public function testGenerateEachPageHasOwnSection(): void
    {
        $service = new LlmsTxtGeneratorService();
        $service->addPage('/about', 'About Us', 'About summary.');

        $outputDir = $this->tempDir . '/public';
        $service->generate($outputDir);

        $content = file_get_contents($outputDir . '/llms.txt');
        $this->assertStringContainsString('## About Us', $content);
        $this->assertStringNotContainsString('## Pages', $content);
    }

    public function testGenerateSectionNameTrimmedAtPipe(): void
    {
        $service = new LlmsTxtGeneratorService();
        $service->addPage('/features', 'Home Features & Upgrades | My Site', 'Features summary.');

        $outputDir = $this->tempDir . '/public';
        $service->generate($outputDir);

        $content = file_get_contents($outputDir . '/llms.txt');
        $this->assertStringContainsString('## Home Features & Upgrades', $content);
        $this->assertStringContainsString('[Home Features & Upgrades | My Site](/features)', $content);
        $this->assertStringNotContainsString('## Home Features & Upgrades | My Site', $content);
    }
}
