<?php

// tests/TestCase.php

namespace Malikad778\MigrationGuard\Tests;

use Malikad778\MigrationGuard\MigrationGuardServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Compatibility shim for older Orchestra Testbench versions
     * that reference this property before Laravel's MakesHttpRequests declares it.
     *
     * @see https://github.com/orchestral/testbench-core/issues/xxx
     */
    public static $latestResponse;

    protected function getPackageProviders($app)
    {
        return [
            MigrationGuardServiceProvider::class,
        ];
    }
}
