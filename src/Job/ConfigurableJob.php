<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Job;

use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Queue\Jobs\JobName;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

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
     * @return array
     */
    public function payload(): array
    {
        if (!empty($this->currentPayload)) {
            return $this->currentPayload;
        }

        $this->handlerPayload = $this->currentPayload = parent::payload();
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

        if ($stdJob) {
            $this->jobName = $stdJob;

            [$class, $method] = JobName::parse($stdJob);

            $this->handlerPayload = $payload['data'];
            $this->listenerEvent = new ListenerEvent($this->instance = $this->resolve($class), $method);

            return;
        }

        $this->getCommandConfiguration($payload);

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
                $this->handlerPayload = json_decode(Arr::get($payload, 'Message'), true);
                $this->jobName = Arr::get($config, 'job_name', $listener);
                $this->command = $listener;

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

                return;
            }
        }
    }
}
