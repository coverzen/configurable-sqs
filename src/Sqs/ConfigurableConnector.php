<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Sqs;

use Aws\Sqs\SqsClient;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Support\Arr;

final class ConfigurableConnector extends SqsConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param array<array-key, mixed> $config
     *
     * @return ConfigurableQueue
     */
    public function connect(array $config): ConfigurableQueue
    {
        $config = $this->getDefaultConfiguration($config);

        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return new ConfigurableQueue(
            new SqsClient($config),
            $config['queue'],
            Arr::get($config, 'prefix', ''),
            Arr::get($config, 'suffix', ''),
            (bool) Arr::get($config, 'has_consumer', true),
            Arr::get($config, 'after_commit', false),
        );
    }
}
