<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\PestPluginDatabaseMigrations\LoadsPackageMigrations;
use JeffersonGoncalves\UsPrivacy\UsPrivacyServiceProvider;
use JeffersonGoncalves\UsPrivacyRequests\Tests\Fixtures\Order;
use JeffersonGoncalves\UsPrivacyRequests\Tests\Fixtures\User;
use JeffersonGoncalves\UsPrivacyRequests\UsPrivacyRequestsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use LoadsPackageMigrations;

    protected function getPackageProviders($app): array
    {
        return [
            UsPrivacyServiceProvider::class,
            UsPrivacyRequestsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('us-privacy.models', [User::class, Order::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadPackageMigrations(UsPrivacyRequestsServiceProvider::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_id')->nullable();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('shipping_address')->nullable();
            $table->unsignedInteger('total');
        });
    }
}
