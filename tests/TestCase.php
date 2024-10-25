<?php

declare(strict_types=1);

namespace Vendor\Package\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Vendor\Package\PackageServiceProvider;

class TestCase extends OrchestraTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @return string[]
     */
    protected function getPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        //
    }
}
