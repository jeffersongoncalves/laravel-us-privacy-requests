<?php

namespace JeffersonGoncalves\UsPrivacyRequests;

use JeffersonGoncalves\UsPrivacyRequests\Commands\OverdueCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UsPrivacyRequestsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('us-privacy-requests')
            ->hasConfigFile()
            ->hasMigrations(['create_us_privacy_consumer_requests_table'])
            ->hasCommand(OverdueCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RequestManager::class);
    }
}
