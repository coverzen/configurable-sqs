<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Job;

use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\JobName;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\ManuallyFailedException;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class ConfigurableJob extends SqsJob
{
    public const TYPE_LARAVEL = 'job';
    public const TYPE_SNS_FROM = 'sns-from';
    public const TYPE_SQS_SIMPLE_PAYLOAD = 'simple-payload';
    public const TYPE_SQS_REGEX_PAYLOAD = 'regex-payload';
    public const TYPE_SQS_UNMATCHED_PAYLOAD = 'unmatched-payload';

    private array $currentPayload = [];
    private array $handlerPayload = [];
    private string $command;
    private string $jobType = self::TYPE_LARAVEL;
    private string $jobName = self::TYPE_SQS_UNMATCHED_PAYLOAD;
    private ListenerEvent $listenerEvent;

    /**
     * {@inheritDoc}
     *
     * @throws JsonException
     */
    public function getRawBody(): string
    {
        /** @var array<array-key, mixed> $rawArray */
        $rawArray = json_decode(json: $this->job['Body'], associative: true, flags: JSON_THROW_ON_ERROR);

        $returnArray = [];

        if (!Arr::exists($rawArray, 'job')) {
            $returnArray['uuid'] = $this->job['MessageId'];
            $returnArray['data'] = $rawArray;
            $returnArray['job'] = SimpleSQSJob::class;
        } else {
            $returnArray = $rawArray;
        }

        return json_encode($returnArray, JSON_THROW_ON_ERROR);
    }

    /**
     * Delete the job, call the "failed" method, and raise the failed job event.
     *
     * @param Throwable|null $e
     *
     * @throws BindingResolutionException
     * @return void
     */
    public function fail($e = null): void
    {
        $this->markAsFailed();

        if ($this->isDeleted()) {
            return;
        }

        /** @var string|null $commandName */
        $commandName = Arr::get($this->payload(), 'data.commandName');

        if (
            $e instanceof TimeoutExceededException &&
            $commandName &&
            in_array(Batchable::class, class_uses_recursive($commandName), true)
        ) {
            /** @var BatchRepository $batchRepository */
            $batchRepository = $this->resolve(BatchRepository::class);

            if (method_exists($batchRepository, 'rollBack')) {
                try {
                    $batchRepository->rollBack();
                } catch (Throwable $e) {
                    Log::error('Job RollBack fail: ' . $e->getMessage());
                }
            }
        }

        try {
            $this->delete();

            $this->failed($e);
        } finally {
            $this->resolve(Dispatcher::class)->dispatch(new JobFailed(
                $this->connectionName,
                $this,
                $e ?: new ManuallyFailedException()
            ));
        }
    }

    /**
     * @param mixed $e
     * @throws BindingResolutionException
     */
    protected function failed($e): void
    {
        $payload = $this->payload();
        $uuid = $payload['uuid'] ?? $this->job['MessageId'];
        $data = $payload['data'] ?? [
            'commandName' => $this->command,
        ];

        if (method_exists($this->instance, 'failed')) {
            $this->instance->failed($data, $e, $uuid);
        }
    }

    /**
     * @return array
     */
    public function payload(): array
    {
        if (!empty($this->currentPayload)) {
            return $this->currentPayload;
        }

        $this->handlerPayload = $this->currentPayload = json_decode(json:$this->getRawBody(), associative: true, flags: JSON_THROW_ON_ERROR);

        $this->payloadAnalyseAndSetEventListener($this->handlerPayload);

        return $this->currentPayload;
    }

    /**
     * @return array
     */
    public function getHandlerPayload(): array
    {
        return $this->handlerPayload;
    }

    /**
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @return string
     */
    public function getJobType(): string
    {
        return $this->jobType;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        if ($this->jobName) {
            return $this->jobName;
        }
        $this->payload();

        return $this->jobName;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveName(): string
    {
        return JobName::resolve($this->getName(), $this->payload());
    }

    /**
     * @return array|bool|null
     */
    public function getUnmatchedListener(): array|bool|null
    {
        return Config::get("queue.connections.{$this->connectionName}.unmatched_listener");
    }

    public function getQueue(): string
    {
        return str_replace(
            [
                Config::get("queue.connections.{$this->connectionName}.suffix", ''),
                Config::get("queue.connections.{$this->connectionName}.prefix", ''),
            ],
            '',
            $this->queue
        );
    }

    /**
     * @throws BindingResolutionException|Exception
     */
    public function fire(): void
    {
        if (!$this->handlerPayload) {
            $this->payload();
        }
        $this->listenerEvent->getClass()->{$this->listenerEvent->getMethod()}($this, $this->handlerPayload);
    }

    /**
     * @param array $payload
     *
     * @return void
     */
    protected function payloadAnalyseAndSetEventListener(array $payload): void
    {
        $stdJob = Arr::get($payload, 'job');

        if ($stdJob && $stdJob !== SimpleSQSJob::class) {
            $this->jobName = $stdJob;

            [$class, $method] = JobName::parse($stdJob);

            $this->handlerPayload = $payload['data'];
            $this->listenerEvent = new ListenerEvent($this->instance = $this->resolve($class), $method);

            return;
        }
        $this->handlerPayload = $this->currentPayload = (Arr::get($payload, 'job') === SimpleSQSJob::class ? $payload['data'] : $payload);
        $this->getCommandConfiguration($this->handlerPayload);

        $this->listenerEvent = new ListenerEvent($this->instance = $this->resolve(ConfigurableCallQueuedHandler::class), 'call');
    }

    /**
     * @param array $payload
     * @return void
     */
    private function getCommandConfiguration(array $payload): void
    {
        $this->jobType = self::TYPE_SQS_UNMATCHED_PAYLOAD;
        $this->command = Arr::get($this->getUnmatchedListener(), 'listener', null) ?? LogMessageListener::class;

        /** @var string $arn */
        $arn = Arr::get($payload, 'TopicArn');

        $configName = "configurable-sqs.{$this->getQueue()}";

        $configs = Config::get($configName, []);

        foreach ($configs as $config) {
            $type = Arr::get($config, 'type');
            $searchKey = Arr::get($config, 'search.key');
            $searchValue = Arr::get($config, 'search.value');
            $listener = Arr::get($config, 'listener');

            if (
                $arn &&
                $type === self::TYPE_SNS_FROM &&
                Arr::get($config, 'arn') === $arn
            ) {
                $this->jobType = $type;
                $this->handlerPayload = json_decode(json: Arr::get($payload, 'Message'), associative: true, flags: JSON_THROW_ON_ERROR);
                $this->jobName = Arr::get($config, 'job_name', $listener);
                $this->command = $listener;
                $this->instance = $this->resolve($this->command);

                return;
            }

            if (
                $type === self::TYPE_SQS_SIMPLE_PAYLOAD &&
                $searchKey &&
                $searchValue &&
                Arr::get($payload, $searchKey) === $searchValue
            ) {
                $this->jobType = $type;
                $this->jobName = Arr::get($config, 'job_name', $listener);
                $this->command = $listener;
                $this->instance = $this->resolve($this->command);

                return;
            }

            if (
                $type === self::TYPE_SQS_REGEX_PAYLOAD &&
                $searchKey &&
                $searchValue &&
                preg_match($searchValue, Arr::get($payload, $searchKey))
            ) {
                $this->jobType = $type;
                $this->jobName = Arr::get($config, 'job_name', $listener);
                $this->command = $listener;
                $this->instance = $this->resolve($this->command);

                return;
            }
        }
    }
}
