<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Sqs;

use Coverzen\ConfigurableSqs\Job\ConfigurableJob;
use Illuminate\Queue\SqsQueue;

final class ConfigurableQueue extends SqsQueue
{
    public function pop($queue = null): ?ConfigurableJob
    {
        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue = $this->getQueue($queue),
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (null !== $response['Messages'] && count($response['Messages']) > 0) {
            return new ConfigurableJob(
                $this->container,
                $this->sqs,
                $response['Messages'][0],
                $this->connectionName,
                $queue
            );
        }

        return null;
    }
}
