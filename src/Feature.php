<?php

declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization;

use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureInterface;
use Calevans\AnswerEngineOptimization\Services\SchemaGeneratorService;
use Calevans\AnswerEngineOptimization\Services\LlmsTxtGeneratorService;
use Calevans\AnswerEngineOptimization\Services\AeoExtractorService;
use Calevans\AnswerEngineOptimization\Services\FaqDataService;
use Calevans\AnswerEngineOptimization\Shortcodes\FaqShortcode;
use EICC\Utils\Container;

class Feature implements FeatureInterface, ConfigurableFeatureInterface
{
    private Container $container;
    private array $config = [];
    private FaqShortcode $faqShortcode;

    public function __construct()
    {
        $this->faqShortcode = new FaqShortcode();
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

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

    public function getName(): string
    {
        return 'AnswerEngineOptimization';
    }

    public function register(EventManager $events): void
    {
        $logger = $this->container->has('logger') ? $this->container->get('logger') : null;
        $appRoot = $this->container->getVariable('app_root') ?? '';

        $schemaService = new SchemaGeneratorService($logger);
        $extractorService = new AeoExtractorService($logger);
        $llmsTxtService = new LlmsTxtGeneratorService($logger);
        $faqDataService = new FaqDataService($logger);

        $this->container->add(SchemaGeneratorService::class, $schemaService);
        $this->container->add(AeoExtractorService::class, $extractorService);
        $this->container->add(LlmsTxtGeneratorService::class, $llmsTxtService);
        $this->container->add(FaqDataService::class, $faqDataService);

        if ($this->container->has(\EICC\StaticForge\Shortcodes\ShortcodeManager::class)) {
            $this->container->get(\EICC\StaticForge\Shortcodes\ShortcodeManager::class)->register($this->faqShortcode);
        }

        $events->registerListener('ROBOTS_TXT_BUILDING', [$this, 'onRobotsTxtBuilding'], 50);
        $events->registerListener('PRE_RENDER', [$this, 'onPreRender'], 50);
        $events->registerListener('MARKDOWN_CONVERTED', [$this, 'onMarkdownConverted'], 50);
        $events->registerListener('POST_RENDER', [$this, 'onPostRender'], 50);
        $events->registerListener('POST_LOOP', [$this, 'onPostLoop'], 50);
    }

    public function onRobotsTxtBuilding(Container $container, array $parameters): array
    {
        if (!isset($parameters['rules'])) {
            return $parameters;
        }

        $rules = $parameters['rules'];
        $aiBots = [
            'OAI-SearchBot', 'ChatGPT-User', 'GPTBot',
            'Anthropic-ai', 'Claude-Web', 'ClaudeBot',
            'Google-Extended', 'PerplexityBot',
            'cohere-ai', 'Bytespider', 'Applebot-Extended',
        ];
        foreach ($aiBots as $bot) {
            if (!isset($rules[$bot])) {
                $rules[$bot] = ['Allow' => ['/']];
            }
        }
        $parameters['rules'] = $rules;
        return $parameters;
    }

    public function onPreRender(Container $container, array $parameters): array
    {
        if ($container->has(\EICC\StaticForge\Shortcodes\ShortcodeManager::class)) {
            $manager = $container->get(\EICC\StaticForge\Shortcodes\ShortcodeManager::class);
            $manager->register($this->faqShortcode);
        }

        if (isset($parameters['file_path']) && file_exists($parameters['file_path'])) {
            $parameters['metadata']['article_modified_time'] = date('c', filemtime($parameters['file_path']));
        }

        return $parameters;
    }

    public function onMarkdownConverted(Container $container, array $parameters): array
    {
        $html = $parameters['html_content'] ?? '';
        $metadata = $parameters['metadata'] ?? [];

        $faqs = $metadata['aeo']['faqs'] ?? [];
        $shortcodeFaqs = $this->faqShortcode->getFaqs();

        $faqDataService = $container->get(FaqDataService::class);
        $appRoot = (string) ($container->getVariable('app_root') ?? '');
        $faqDataService->load($appRoot, $this->config['faq_data_file'] ?? null);
        $dataFaqs = $faqDataService->resolve($html);

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
            // Store in metadata so it survives the MARKDOWN_CONVERTED → POST_RENDER boundary.
            // The MarkdownRendererService only forwards 'html_content' and 'metadata' from
            // MARKDOWN_CONVERTED results; top-level parameters are dropped.
            $parameters['metadata']['aeo_faq_schema'] = $faqSchema;
        }

        $extractorService = $container->get(AeoExtractorService::class);
        $summary = $metadata['aeo']['key_takeaways'] ?? $extractorService->extractSummary($html);
        $parameters['aeo_summary'] = $summary;

        $this->faqShortcode->reset();

        return $parameters;
    }

    public function onPostRender(Container $container, array $parameters): array
    {
        $schemaService = $container->get(SchemaGeneratorService::class);
        $metadata = $parameters['metadata'] ?? [];
        $noLlms = !empty($metadata['no_llms']);

        $siteConfig  = $container->getVariable('site_config') ?? [];
        $siteBaseUrl = rtrim((string)($container->getVariable('SITE_BASE_URL') ?? '/'), '/');
        $appRoot     = rtrim((string)($container->getVariable('app_root') ?? ''), '/');

        // Build the canonical HTML URL for this page once; reused for breadcrumb and llms.txt
        $pageUrl = $parameters['file_url'] ?? '';
        if (empty($pageUrl) && !empty($parameters['output_path']) && !empty($appRoot)) {
            $publicPath = $appRoot . '/public/';
            if (str_starts_with($parameters['output_path'], $publicPath)) {
                $pageUrl = $siteBaseUrl . '/' . ltrim(substr($parameters['output_path'], strlen($publicPath)), '/');
            }
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $metadata['title'] ?? 'Untitled',
            'dateModified' => $metadata['article_modified_time'] ?? date('c'),
            'publisher' => [
                '@type' => 'Organization',
                'name' => $siteConfig['site']['name'] ?? 'Untitled Site',
            ]
        ];

        $logo = $siteConfig['social']['default_image'] ?? null;
        if ($logo) {
            $schema['publisher']['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo
            ];
        }

        $scripts = $schemaService->generate($schema);

        $faqSchema = $parameters['metadata']['aeo_faq_schema'] ?? $parameters['aeo_faq_schema'] ?? null;
        if (!empty($faqSchema)) {
            $scripts .= "\n" . $schemaService->generate($faqSchema);
        }

        // BreadcrumbList — skip on the home page (index.html)
        $homeUrl = $siteBaseUrl . '/';
        $isHomePage = !empty($pageUrl) && (
            rtrim($pageUrl, '/') === rtrim($homeUrl, '/') ||
            str_ends_with($pageUrl, '/index.html')
        );
        if (!empty($pageUrl) && !$isHomePage && !str_starts_with($pageUrl, '//') && !str_contains($pageUrl, $appRoot)) {
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
                        'name' => $metadata['title'] ?? 'Untitled',
                        'item' => $pageUrl,
                    ],
                ],
            ];
            $scripts .= "\n" . $schemaService->generate($breadcrumb);
        }

        $scripts .= "\n<link rel=\"sitemap\" type=\"application/xml\" href=\"{$siteBaseUrl}/sitemap.xml\">";

        if (!$noLlms) {
            $scripts .= "\n<link rel=\"llms\" href=\"{$siteBaseUrl}/llms.txt\">\n";
        }

        // Inject the tags into the head of the document
        if (isset($parameters['rendered_content'])) {
            $parameters['rendered_content'] = str_replace('</head>', $scripts . "\n</head>", $parameters['rendered_content']);
        }

        if (!$noLlms && isset($parameters['file_path']) && isset($parameters['output_path'])) {
            $sourcePath = $parameters['file_path'];
            if (pathinfo($sourcePath, PATHINFO_EXTENSION) === 'md') {
                $publicPath = $parameters['output_path'];
                $mdPublicPath = preg_replace('/\.html$/', '.md', $publicPath);

                $sourceContent = file_get_contents($sourcePath);
                $rawContent = preg_replace('/^---[\s\S]*?---[\r\n]+/', '', $sourceContent);

                $dir = dirname($mdPublicPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                file_put_contents($mdPublicPath, trim($rawContent ?? ''));
            }
        }

        // Validate the URL. If it's empty, contains internal filesystem paths, or page is excluded, skip it entirely.
        if (!$noLlms && !empty($pageUrl) && !str_starts_with($pageUrl, '//') && !str_contains($pageUrl, $appRoot)) {
            $title = $metadata['title'] ?? 'Untitled';
            $summary = $parameters['aeo_summary'] ?? $metadata['description'] ?? '';

            // Point the AI directly to the clean .md copy we generated
            $llmsUrl = $pageUrl;
            if (isset($parameters['file_path']) && pathinfo($parameters['file_path'], PATHINFO_EXTENSION) === 'md') {
                $llmsUrl = preg_replace('/\.html$/', '.md', $pageUrl);
            }

            $llmsTxtService = $container->get(LlmsTxtGeneratorService::class);
            $llmsTxtService->addPage($llmsUrl, $title, $summary);
        }

        return $parameters;
    }

    public function onPostLoop(Container $container, array $parameters): array
    {
        $llmsTxtService = $container->get(LlmsTxtGeneratorService::class);
        $outputDir = $container->getVariable('OUTPUT_DIR');

        if (is_string($outputDir) && $outputDir !== '' && $llmsTxtService->hasPages()) {
            $siteConfig      = $container->getVariable('site_config') ?? [];
            $siteName        = (string) ($siteConfig['site']['name'] ?? '');
            $siteDescription = (string) ($siteConfig['site']['tagline'] ?? $siteConfig['site']['description'] ?? '');
            $llmsTxtService->generate($outputDir, $siteName, $siteDescription);
        }

        return $parameters;
    }
}
