<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs;

use Coverzen\ConfigurableSqs\Sqs\ConfigurableConnector;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;

final class ConfigurableSqsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/configurable-sqs.php' => config_path('configurable-sqs.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->app->afterResolving(QueueManager::class, function (QueueManager $manager) {
            $manager->addConnector('configurable-sqs', fn () => new ConfigurableConnector());
        });
    }
}
