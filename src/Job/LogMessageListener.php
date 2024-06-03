<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Job;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Default listener for logging unmatched messages from the queue.
 */
final class LogMessageListener
{
    /**
     * @param ConfigurableJob $job
     * @param array|bool|null $logger
     *
     * @return void
     */
    public function handle(ConfigurableJob $job, array|bool|null $logger): void
    {
        $message = json_encode($job->payload());
        $channel = Arr::get($logger, 'channel', null);
        Log::channel($channel)->info("Unmatched message for queue {$job->getQueue()}: {$message}");
    }
}
