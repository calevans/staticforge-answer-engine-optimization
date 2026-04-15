<?php
declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Calevans\AnswerEngineOptimization\Shortcodes\FaqShortcode;

class FaqShortcodeTest extends TestCase
{
    public function testProcessAndGetFaqs(): void
    {
        $shortcode = new FaqShortcode();
        $this->assertEquals('aeo_faq', $shortcode->getName());

        $content = "This is the answer.";
        $attributes = ['question' => 'What is this?'];

        $result = $shortcode->handle($attributes, $content);

        $this->assertStringContainsString('<details class="aeo-faq">', $result);
        $this->assertStringContainsString('<summary>What is this?</summary>', $result);
        $this->assertStringContainsString('<div class="aeo-faq-content">', $result);
        $this->assertStringContainsString('This is the answer.', $result);

        $faqs = $shortcode->getFaqs();
        $this->assertCount(1, $faqs);
        $this->assertEquals('What is this?', $faqs[0]['question']);
        $this->assertEquals('This is the answer.', $faqs[0]['answer']);

        $shortcode->reset();
        $this->assertCount(0, $shortcode->getFaqs());
    }

    public function testEscapesQuestion(): void
    {
        $shortcode = new FaqShortcode();
        $result = $shortcode->handle(['question' => 'Is 1 < 2?'], 'answer');
        $this->assertStringContainsString('Is 1 &lt; 2?', $result);
    }
}
