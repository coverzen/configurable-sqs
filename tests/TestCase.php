<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests;

use Coverzen\ConfigurableSqs\ConfigurableSqsServiceProvider;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $localstack = getenv('LOCALSTACK') ?: 'ddev-configurable-sqs-localstack';
        Config::set('app.faker_locale', 'it_IT');
        Config::set('queue.connections.test', [
            'driver' => 'configurable-sqs',
            'key' => 'your-public',
            'secret' => 'your-secret',
            'prefix' => "http://{$localstack}:4566/000000000000/",
            'queue' => 'test-queue',
            'endpoint' => "http://{$localstack}:4566",
            'suffix' => '',
            'region' => 'eu-south-1',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            ConfigurableSqsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
    }
}
