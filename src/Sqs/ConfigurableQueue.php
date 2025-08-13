<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Sqs;

use Aws\Sqs\SqsClient;
use Coverzen\ConfigurableSqs\Job\ConfigurableJob;
use Coverzen\ConfigurableSqs\Job\SimpleSQSJob;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\InvalidPayloadException;
use Illuminate\Queue\SqsQueue;

final class ConfigurableQueue extends SqsQueue
{
    /**
     * Connector is enabled for consume sqs messages.
     *
     * @var bool $hasConsumer
     */
    protected bool $hasConsumer;

    /**
     * Create a new Amazon SQS queue instance.
     *
     * @param  SqsClient  $sqs
     * @param  string  $default
     * @param  string  $prefix
     * @param  string  $suffix
     * @param  bool $hasConsumer
     * @param bool $dispatchAfterCommit
     *
     * @return void
     */
    public function __construct(
        SqsClient $sqs,
        $default,
        $prefix = '',
        $suffix = '',
        bool $hasConsumer = true,
        bool $dispatchAfterCommit = false,
    ) {
        parent::__construct($sqs, $default, $prefix, $suffix, $dispatchAfterCommit);
        $this->hasConsumer = $hasConsumer;
    }

    /**
     * {@inheritdoc}
     */
    protected function createPayload($job, $queue, $data = ''): false|string
    {
        if (!$job instanceof SimpleSQSJob) {
            return parent::createPayload($job, $queue, $data);
        }

        $jsonPayload = [
            'event' => $job->getEvent() ?? $job::class . '@event',
            'data' => $job->getPayload(),
        ];

        $json = json_encode(
            $jsonPayload,
            JSON_UNESCAPED_UNICODE
        );

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidPayloadException('Unable to JSON encode payload. Error (' . json_last_error() . '): ' . json_last_error_msg(), $jsonPayload);
        }

        return $json;
    }

    public function pop($queue = null): ?ConfigurableJob
    {
        if (!$this->hasConsumer) {
            return null;
        }

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
