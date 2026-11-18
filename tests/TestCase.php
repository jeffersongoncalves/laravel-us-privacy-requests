<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Tests;

use JeffersonGoncalves\UsPrivacyRequests\UsPrivacyRequestsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            UsPrivacyRequestsServiceProvider::class,
        ];
    }
}
