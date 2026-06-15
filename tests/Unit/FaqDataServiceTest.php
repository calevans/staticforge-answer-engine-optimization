<?php

declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Calevans\AnswerEngineOptimization\Services\FaqDataService;

class FaqDataServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/faq_test_' . uniqid();
        mkdir($this->tempDir . '/content/assets', 0777, true);
    }

    protected function tearDown(): void
    {
        $default = $this->tempDir . '/content/assets/faq.json';
        $custom  = $this->tempDir . '/custom/faq.json';
        if (file_exists($default)) {
            unlink($default);
        }
        if (file_exists($custom)) {
            unlink($custom);
            rmdir($this->tempDir . '/custom');
        }
        rmdir($this->tempDir . '/content/assets');
        rmdir($this->tempDir . '/content');
        rmdir($this->tempDir);
    }

    private function writeJson(string $path, mixed $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    // --- load() ---

    public function testLoadReturnsEmptyArrayWhenNoFileExists(): void
    {
        $service = new FaqDataService();
        $index = $service->load($this->tempDir);

        $this->assertSame([], $index);
    }

    public function testLoadIndexesDefaultFaqJsonFile(): void
    {
        $this->writeJson($this->tempDir . '/content/assets/faq.json', [
            ['id' => 'q1', 'question' => 'What?', 'answer' => 'This.'],
            ['id' => 'q2', 'question' => 'Why?',  'answer' => 'Because.'],
        ]);

        $service = new FaqDataService();
        $index = $service->load($this->tempDir);

        $this->assertArrayHasKey('q1', $index);
        $this->assertSame('What?', $index['q1']['question']);
        $this->assertSame('This.', $index['q1']['answer']);
        $this->assertArrayHasKey('q2', $index);
    }

    public function testLoadUsesConfiguredPathOverDefault(): void
    {
        $this->writeJson($this->tempDir . '/custom/faq.json', [
            ['id' => 'custom-q', 'question' => 'Custom?', 'answer' => 'Yes.'],
        ]);
        // Default file deliberately absent.

        $service = new FaqDataService();
        $index = $service->load($this->tempDir, 'custom/faq.json');

        $this->assertArrayHasKey('custom-q', $index);
        $this->assertSame('Custom?', $index['custom-q']['question']);
    }

    public function testLoadIgnoresEntriesMissingRequiredKeys(): void
    {
        $this->writeJson($this->tempDir . '/content/assets/faq.json', [
            ['id' => 'ok',  'question' => 'Good?', 'answer' => 'Yes.'],
            ['question' => 'No id.', 'answer' => 'Missing id.'],
            ['id' => 'no-answer', 'question' => 'Missing answer?'],
        ]);

        $service = new FaqDataService();
        $index = $service->load($this->tempDir);

        $this->assertCount(1, $index);
        $this->assertArrayHasKey('ok', $index);
    }

    public function testLoadCachesIndexOnSubsequentCalls(): void
    {
        $path = $this->tempDir . '/content/assets/faq.json';
        $this->writeJson($path, [
            ['id' => 'q1', 'question' => 'First?', 'answer' => 'Yes.'],
        ]);

        $service = new FaqDataService();
        $index1 = $service->load($this->tempDir);

        // Overwrite the file — a second call must still return the cached result.
        $this->writeJson($path, [
            ['id' => 'q2', 'question' => 'Second?', 'answer' => 'Also yes.'],
        ]);
        $index2 = $service->load($this->tempDir);

        $this->assertSame($index1, $index2);
        $this->assertArrayHasKey('q1', $index2);
        $this->assertArrayNotHasKey('q2', $index2);
    }

    // --- resolve() ---

    public function testResolveReturnsEmptyWhenIndexIsEmpty(): void
    {
        $service = new FaqDataService();
        $service->load($this->tempDir); // no file → empty index

        $result = $service->resolve('<div data-faq="anything"></div>');

        $this->assertSame([], $result);
    }

    public function testResolveMatchesFaqsByDataAttribute(): void
    {
        $this->writeJson($this->tempDir . '/content/assets/faq.json', [
            ['id' => 'q1', 'question' => 'What?', 'answer' => 'This.'],
            ['id' => 'q2', 'question' => 'Why?',  'answer' => 'Because.'],
        ]);

        $service = new FaqDataService();
        $service->load($this->tempDir);

        $html   = '<p data-faq="q2"></p><p data-faq="q1"></p>';
        $result = $service->resolve($html);

        $this->assertCount(2, $result);
        // Order follows appearance in HTML
        $this->assertSame('Why?',  $result[0]['question']);
        $this->assertSame('What?', $result[1]['question']);
    }

    public function testResolveDeduplicatesRepeatedAttributes(): void
    {
        $this->writeJson($this->tempDir . '/content/assets/faq.json', [
            ['id' => 'q1', 'question' => 'What?', 'answer' => 'This.'],
        ]);

        $service = new FaqDataService();
        $service->load($this->tempDir);

        $html   = '<p data-faq="q1"></p><p data-faq="q1"></p>';
        $result = $service->resolve($html);

        $this->assertCount(1, $result);
    }

    public function testResolveSkipsUnknownIds(): void
    {
        $this->writeJson($this->tempDir . '/content/assets/faq.json', [
            ['id' => 'q1', 'question' => 'What?', 'answer' => 'This.'],
        ]);

        $service = new FaqDataService();
        $service->load($this->tempDir);

        $result = $service->resolve('<p data-faq="unknown"></p>');

        $this->assertSame([], $result);
    }

    public function testResolveReturnsEmptyForHtmlWithNoDataFaqAttributes(): void
    {
        $this->writeJson($this->tempDir . '/content/assets/faq.json', [
            ['id' => 'q1', 'question' => 'What?', 'answer' => 'This.'],
        ]);

        $service = new FaqDataService();
        $service->load($this->tempDir);

        $result = $service->resolve('<p>No attributes here.</p>');

        $this->assertSame([], $result);
    }
}
