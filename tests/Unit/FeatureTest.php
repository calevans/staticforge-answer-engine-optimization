<?php
declare(strict_types=1);

namespace Calevans\AnswerEngineOptimization\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Calevans\AnswerEngineOptimization\Feature;
use EICC\StaticForge\Core\Events\RobotsTxtBuildingEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
use EICC\Utils\Container;
use EICC\Utils\Log;

class FeatureTest extends TestCase
{
    private Container $container;
    private Feature $feature;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->setVariable('app_root', '/tmp');
        $this->container->add('logger', $this->createMock(Log::class));

        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
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
    }

    public function testOnRobotsTxtBuilding(): void
    {
        $event = new RobotsTxtBuildingEvent('ROBOTS_TXT_BUILDING', []);
        $this->feature->onRobotsTxtBuilding($event);

        $this->assertArrayHasKey('OAI-SearchBot', $event->rules);
        $this->assertEquals(['Allow' => ['/']], $event->rules['OAI-SearchBot']);
        $this->assertArrayHasKey('ChatGPT-User', $event->rules);
        $this->assertArrayHasKey('Claude-Web', $event->rules);
    }
}
