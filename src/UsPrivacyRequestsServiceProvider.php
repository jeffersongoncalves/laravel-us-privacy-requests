<?php

namespace JeffersonGoncalves\UsPrivacyRequests;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UsPrivacyRequestsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-us-privacy-requests')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations();
    }
}
