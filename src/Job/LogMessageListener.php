<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Job;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Default listener for logging unmatched messages from the queue.
 */
final class LogMessageListener
{
    /**
     * @param ConfigurableJob $job
     * @param array<array-key, mixed>|bool|null $logger
     *
     * @throws JsonException
     * @return void
     */
    public function handle(ConfigurableJob $job, array|bool|null $logger): void
    {
        $message = json_encode($job->payload(), JSON_THROW_ON_ERROR);
        $channel = is_array($logger) ? Arr::get($logger, 'channel') : null;
        Log::channel($channel)->info("Unmatched message for queue {$job->getQueue()}: {$message}");
    }
}
