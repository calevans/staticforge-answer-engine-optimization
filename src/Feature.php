<?php

declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\Events\RobotsTxtBuildingEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureInterface;
use Calevans\AnswerEngineOptimization\Services\SchemaGeneratorService;
use Calevans\AnswerEngineOptimization\Services\LlmsTxtGeneratorService;
use Calevans\AnswerEngineOptimization\Services\AeoExtractorService;
use Calevans\AnswerEngineOptimization\Services\FaqDataService;
use Calevans\AnswerEngineOptimization\Shortcodes\FaqShortcode;
use EICC\Utils\Container;

class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'AnswerEngineOptimization';
    /** @var array<string, mixed> */
    private array $config = [];
    private FaqShortcode $faqShortcode;
    private SchemaGeneratorService $schemaService;
    private AeoExtractorService $extractorService;
    private LlmsTxtGeneratorService $llmsTxtService;
    private FaqDataService $faqDataService;

    public function __construct(
        Container $container,
        SchemaGeneratorService $schemaService,
        AeoExtractorService $extractorService,
        LlmsTxtGeneratorService $llmsTxtService,
        FaqDataService $faqDataService,
        FaqShortcode $faqShortcode
    ) {
        $this->container = $container;
        $this->schemaService = $schemaService;
        $this->extractorService = $extractorService;
        $this->llmsTxtService = $llmsTxtService;
        $this->faqDataService = $faqDataService;
        $this->faqShortcode = $faqShortcode;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function configure(array $config): void
    {
        $this->config = $config['answer_engine_optimization'] ?? [];
    }

    public function getRequiredConfig(): array
    {
        return [];
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);

        if ($this->container->has(\EICC\StaticForge\Shortcodes\ShortcodeManager::class)) {
            $this->container->get(\EICC\StaticForge\Shortcodes\ShortcodeManager::class)->register($this->faqShortcode);
        }
    }

    #[EventListener('ROBOTS_TXT_BUILDING', priority: 50)]
    public function onRobotsTxtBuilding(RobotsTxtBuildingEvent $event): void
    {
        $aiBots = [
            'OAI-SearchBot', 'ChatGPT-User', 'GPTBot',
            'Anthropic-ai', 'Claude-Web', 'ClaudeBot',
            'Google-Extended', 'PerplexityBot',
            'cohere-ai', 'Bytespider', 'Applebot-Extended',
        ];
        foreach ($aiBots as $bot) {
            if (!isset($event->rules[$bot])) {
                $event->rules[$bot] = ['Allow' => ['/']];
            }
        }
    }

    #[EventListener('PRE_RENDER', priority: 50)]
    public function onPreRender(RenderEvent $event): void
    {
        if ($this->container->has(\EICC\StaticForge\Shortcodes\ShortcodeManager::class)) {
            $manager = $this->container->get(\EICC\StaticForge\Shortcodes\ShortcodeManager::class);
            $manager->register($this->faqShortcode);
        }

        if ($event->filePath !== '' && file_exists($event->filePath)) {
            $mtime = filemtime($event->filePath);
            if ($mtime !== false) {
                $event->metadata['article_modified_time'] = date('c', $mtime);
            }
        }
    }

    #[EventListener('MARKDOWN_CONVERTED', priority: 50)]
    public function onMarkdownConverted(RenderEvent $event): void
    {
        $html = $event->renderedContent ?? '';
        $metadata = $event->metadata;

        $faqs = $metadata['aeo']['faqs'] ?? [];
        $shortcodeFaqs = $this->faqShortcode->getFaqs();

        $appRoot = (string) ($this->container->getVariable('app_root') ?? '');
        $this->faqDataService->load($appRoot, $this->config['faq_data_file'] ?? null);
        $dataFaqs = $this->faqDataService->resolve($html);

        $allFaqs = array_merge($faqs, $shortcodeFaqs, $dataFaqs);

        if (!empty($allFaqs)) {
            $faqSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => []
            ];
            foreach ($allFaqs as $faq) {
                $faqSchema['mainEntity'][] = [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq['answer']
                    ]
                ];
            }
            // Store in metadata so it survives the MARKDOWN_CONVERTED → RENDER boundary:
            // MarkdownRendererService only copies renderedContent/metadata back from the
            // sub-event it fires for MARKDOWN_CONVERTED; extra is scoped to that sub-event
            // and does not propagate back out.
            $event->metadata['aeo_faq_schema'] = $faqSchema;
        }

        $summary = $metadata['aeo']['key_takeaways'] ?? $this->extractorService->extractSummary($html);
        $event->extra['aeo_summary'] = $summary;

        $this->faqShortcode->reset();
    }

    #[EventListener('POST_RENDER', priority: 50)]
    public function onPostRender(RenderEvent $event): void
    {
        $metadata = $event->metadata;
        $noLlms = !empty($metadata['no_llms']);

        $siteConfig  = $this->container->getVariable('site_config') ?? [];
        $siteBaseUrl = rtrim((string)($this->container->getVariable('SITE_BASE_URL') ?? '/'), '/');
        $appRoot     = rtrim((string)($this->container->getVariable('app_root') ?? ''), '/');

        // Build the canonical HTML URL for this page once; reused for breadcrumb and llms.txt
        $pageUrl = $event->fileUrl;
        if (empty($pageUrl) && !empty($event->outputPath) && !empty($appRoot)) {
            $publicPath = $appRoot . '/public/';
            if (str_starts_with($event->outputPath, $publicPath)) {
                $pageUrl = $siteBaseUrl . '/' . ltrim(substr($event->outputPath, strlen($publicPath)), '/');
            }
        }

        // Omit rather than fabricate: a title-less headline or a build-time
        // "modified now" timestamp would be schema-valid but false, which is
        // exactly the kind of noise that makes AI tools distrust a site's data.
        $schema = ['@context' => 'https://schema.org', '@type' => 'Article'];

        if (($title = $metadata['title'] ?? null) !== null && $title !== '') {
            $schema['headline'] = $title;
        }
        if (($modified = $metadata['article_modified_time'] ?? null) !== null && $modified !== '') {
            $schema['dateModified'] = $modified;
        }

        $siteName = $siteConfig['site']['name'] ?? null;
        if (is_string($siteName) && $siteName !== '') {
            $publisher = ['@type' => 'Organization', 'name' => $siteName];
            $logo = $siteConfig['social']['default_image'] ?? null;
            if ($logo) {
                $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
            }
            $schema['publisher'] = $publisher;
        }

        $scripts = $this->schemaService->generate($schema);

        $faqSchema = $event->metadata['aeo_faq_schema'] ?? null;
        if (!empty($faqSchema)) {
            $scripts .= "\n" . $this->schemaService->generate($faqSchema);
        }

        // BreadcrumbList — skip on the home page (index.html)
        $homeUrl = $siteBaseUrl . '/';
        $isHomePage = !empty($pageUrl) && (
            rtrim($pageUrl, '/') === rtrim($homeUrl, '/') ||
            str_ends_with($pageUrl, '/index.html')
        );
        $pageTitle = $metadata['title'] ?? null;
        if (
            !empty($pageUrl) && !$isHomePage && $pageTitle !== null && $pageTitle !== ''
            && !str_starts_with($pageUrl, '//') && !str_contains($pageUrl, $appRoot)
        ) {
            $breadcrumb = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => $homeUrl,
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $pageTitle,
                        'item' => $pageUrl,
                    ],
                ],
            ];
            $scripts .= "\n" . $this->schemaService->generate($breadcrumb);
        }

        $scripts .= "\n<link rel=\"sitemap\" type=\"application/xml\" href=\"{$siteBaseUrl}/sitemap.xml\">";

        if (!$noLlms) {
            $scripts .= "\n<link rel=\"llms\" href=\"{$siteBaseUrl}/llms.txt\">\n";
        }

        // Inject the tags into the head of the document
        if ($event->renderedContent !== null) {
            $event->renderedContent = str_replace('</head>', $scripts . "\n</head>", $event->renderedContent);
        }

        if (!$noLlms && $event->filePath !== '' && $event->outputPath !== null) {
            $sourcePath = $event->filePath;
            if (pathinfo($sourcePath, PATHINFO_EXTENSION) === 'md') {
                $publicPath = $event->outputPath;
                $mdPublicPath = preg_replace('/\.html$/', '.md', $publicPath);
                $sourceContent = file_get_contents($sourcePath);

                if ($mdPublicPath !== null && $sourceContent !== false) {
                    $rawContent = preg_replace('/^---[\s\S]*?---[\r\n]+/', '', $sourceContent);

                    $dir = dirname($mdPublicPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    file_put_contents($mdPublicPath, trim($rawContent ?? ''));
                }
            }
        }

        // Validate the URL. If it's empty, contains internal filesystem paths,
        // has no title, or page is excluded, skip it entirely.
        if (
            !$noLlms && !empty($pageUrl) && !empty($metadata['title'])
            && !str_starts_with($pageUrl, '//') && !str_contains($pageUrl, $appRoot)
        ) {
            $title = $metadata['title'];
            $summary = $event->extra['aeo_summary'] ?? $metadata['description'] ?? '';

            // Point the AI directly to the clean .md copy we generated
            $llmsUrl = $pageUrl;
            if ($event->filePath !== '' && pathinfo($event->filePath, PATHINFO_EXTENSION) === 'md') {
                $llmsUrl = preg_replace('/\.html$/', '.md', $pageUrl) ?? $pageUrl;
            }

            $this->llmsTxtService->addPage($llmsUrl, $title, $summary);
        }
    }

    #[EventListener('POST_LOOP', priority: 50)]
    public function onPostLoop(Event $event): void
    {
        $outputDir = $this->container->getVariable('OUTPUT_DIR');

        if (is_string($outputDir) && $outputDir !== '' && $this->llmsTxtService->hasPages()) {
            $siteConfig      = $this->container->getVariable('site_config') ?? [];
            $siteName        = (string) ($siteConfig['site']['name'] ?? '');
            $siteDescription = (string) ($siteConfig['site']['tagline'] ?? $siteConfig['site']['description'] ?? '');
            $this->llmsTxtService->generate($outputDir, $siteName, $siteDescription);
        }
    }
}
