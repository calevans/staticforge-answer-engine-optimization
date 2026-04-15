<?php
declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Calevans\AnswerEngineOptimization\Feature;
use Calevans\AnswerEngineOptimization\Services\SchemaGeneratorService;
use Calevans\AnswerEngineOptimization\Services\LlmsTxtGeneratorService;
use Calevans\AnswerEngineOptimization\Services\AeoExtractorService;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class FeatureTest extends TestCase
{
    private Container $container;
    private Feature $feature;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->setVariable('app_root', '/tmp');
        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
    }

    public function testGetName(): void
    {
        $this->assertEquals('AnswerEngineOptimization', $this->feature->getName());
    }

    public function testRegisterAndConfigure(): void
    {
        $this->feature->configure(['answer_engine_optimization' => ['enabled' => true]]);

        $eventManager = $this->createMock(EventManager::class);
        $eventManager->expects($this->exactly(5))
                     ->method('registerListener');

        $this->feature->register($eventManager);

        $this->assertTrue($this->container->has(SchemaGeneratorService::class));
        $this->assertTrue($this->container->has(AeoExtractorService::class));
        $this->assertTrue($this->container->has(LlmsTxtGeneratorService::class));
    }

    public function testOnRobotsTxtBuilding(): void
    {
        $parameters = ['rules' => []];
        $result = $this->feature->onRobotsTxtBuilding($this->container, $parameters);

        $rules = $result['rules'];
        $this->assertArrayHasKey('OAI-SearchBot', $rules);
        $this->assertEquals(['Allow' => ['/']], $rules['OAI-SearchBot']);
        $this->assertArrayHasKey('ChatGPT-User', $rules);
        $this->assertArrayHasKey('Claude-Web', $rules);
    }
}
