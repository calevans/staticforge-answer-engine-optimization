<?php

declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Shortcodes;

use EICC\StaticForge\Shortcodes\ShortcodeInterface;

class FaqShortcode implements ShortcodeInterface
{
    private array $faqs = [];

    public function getName(): string
    {
        return 'aeo_faq';
    }

    public function handle(array $attributes, string $content = ''): string
    {
        $question = $attributes['question'] ?? 'Question';
        $answer = trim($content);

        $this->faqs[] = [
            'question' => $question,
            'answer' => strip_tags($answer)
        ];

        $escapedQuestion = htmlspecialchars($question, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<details class="aeo-faq">
    <summary>{$escapedQuestion}</summary>
    <div class="aeo-faq-content">
        {$answer}
    </div>
</details>
HTML;
    }

    public function getFaqs(): array
    {
        return $this->faqs;
    }

    public function reset(): void
    {
        $this->faqs = [];
    }
}
