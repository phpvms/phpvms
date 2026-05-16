<?php

namespace Tests;

use App\Models\Module as ModuleModel;
use App\Support\Modules\DatabaseActivator;
use Nwidart\Modules\Module as NwidartModule;

class DatabaseActivatorTest extends TestCase
{
    public function test_enable_creates_modules_row_when_none_exists(): void
    {
        $name = 'PhpvmsRegressionFixtureModule';

        $this->assertFalse(
            ModuleModel::where('name', $name)->exists(),
            'Test setup invariant violated: row already exists.'
        );

        $nwidartModule = $this->getMockBuilder(NwidartModule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['registerAliases', 'registerProviders', 'getCachedServicesPath', 'getName'])
            ->getMock();
        $nwidartModule->method('getName')->willReturn($name);

        $activator = app(DatabaseActivator::class);

        $activator->enable($nwidartModule);

        $this->assertTrue(
            ModuleModel::where('name', $name)->where('enabled', true)->exists(),
            'Expected DatabaseActivator::enable() to create + enable a modules row.'
        );
    }
}
