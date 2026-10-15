<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Unit\Job;

use Aws\Sqs\SqsClient;
use Coverzen\ConfigurableSqs\Job\ConfigurableJob;
use Coverzen\ConfigurableSqs\Tests\TestCase;
use Illuminate\Container\Container;
use Illuminate\Queue\ManuallyFailedException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use stdClass;
use TiMacDonald\Log\LogEntry;
use TiMacDonald\Log\LogFake;

class ConfigurableJobTest extends TestCase
{
    private string $queueName;

    private string $queueUrl;

    private SqsClient|MockInterface $mockedSqsClient;

    private Container|MockInterface $mockedContainer;

    private string $mockedJob;

    private array $mockedData;

    private string $mockedPayload;

    private string $mockedMessageId;

    private string $mockedReceiptHandle;

    private array $mockedJobData;

    private array $mockedPlainSnsPayload;

    private string $mockedSnsPayload;

    private array $mockedSnsJobData;

    private array $mockedJobStrictKeyData;

    private array $mockedRegexPayload;

    private array $mockedJobRegexKeyData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queueName = Config::get('queue.connections.test.queue');

        $this->queueUrl = Config::get('queue.connections.test.prefix') . $this->queueName;

        $this->mockedSqsClient = Mockery::mock(SqsClient::class)->makePartial();

        $this->mockedContainer = Mockery::mock(Container::class);

        $this->mockedJob = 'foo';
        $this->mockedData = ['data'];
        $this->mockedPayload = json_encode(['job' => $this->mockedJob, 'data' => $this->mockedData, 'attempts' => 1]);
        $this->mockedMessageId = 'e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81';
        $this->mockedReceiptHandle = '0NNAq8PwvXuWv5gMtS9DJ8qEdyiUwbAjpp45w2m6M4SJ1Y+PxCh7R930NRB8ylSacEmoSnW18bgd4nK\/O6ctE+VFVul4eD23mA07vVoSnPI4F\/voI1eNCp6Iax0ktGmhlNVzBwaZHEr91BRtqTRM3QKd2ASF8u+IQaSwyl\/DGK+P1+dqUOodvOVtExJwdyDLy1glZVgm85Yw9Jf5yZEEErqRwzYz\/qSigdvW4sm2l7e4phRol\/+IjMtovOyH\/ukueYdlVbQ4OshQLENhUKe7RNN5i6bE\/e5x9bnPhfj2gbM';

        $this->mockedJobData = [
            'Body' => $this->mockedPayload,
            'MD5OfBody' => md5($this->mockedPayload),
            'ReceiptHandle' => $this->mockedReceiptHandle,
            'MessageId' => $this->mockedMessageId,
            'Attributes' => ['ApproximateReceiveCount' => 1],
        ];

        $this->mockedPlainSnsPayload = [
            'Records' => [
                [
                    's3' => [
                        'bucket' => [
                            'name' => 'mybucket',
                            'ownerIdentity' => [
                                'principalId' => 'EXAMPLE',
                            ],
                            'arn' => 'arn:aws:s3:::mybucket',
                        ],
                        'object' => [
                            'key' => 'test/key',
                            'size' => 1024,
                            'eTag' => '0123456789abcdef0123456789abcdef',
                            'sequencer' => '0A1B2C3D4E5F678901',
                        ],
                    ],
                ],
            ],
        ];

        $this->mockedSnsPayload = json_encode([
            'Type' => 'Notification',
            'MessageId' => 'e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81',
            'TopicArn' => 'arn:aws:sns:eu-south-1:000000000000:MyTopic',
            'Subject' => 'My First Message',
            'Message' => json_encode($this->mockedPlainSnsPayload),
            'Timestamp' => '2012-04-25T21:49:25.719Z',
            'SignatureVersion' => '1',
            'Signature' => 'EXAMPLE',
            'SigningCertURL' => 'EXAMPLE',
            'UnsubscribeURL' => 'EXAMPLE',
        ]);

        $this->mockedSnsJobData = [
            'Body' => $this->mockedSnsPayload,
            'MD5OfBody' => md5($this->mockedSnsPayload),
            'ReceiptHandle' => $this->mockedReceiptHandle,
            'MessageId' => $this->mockedMessageId,
            'Attributes' => ['ApproximateReceiveCount' => 1],
        ];

        $this->mockedJobStrictKeyData = [
            'Body' => json_encode(['key' => 'value']),
            'MD5OfBody' => md5(json_encode(['key' => 'value'])),
            'ReceiptHandle' => $this->mockedReceiptHandle,
            'MessageId' => $this->mockedMessageId,
            'Attributes' => ['ApproximateReceiveCount' => 1],
        ];

        $value = "test-{$this->faker->word}";

        $this->mockedRegexPayload = ['key' => $value];

        $this->mockedJobRegexKeyData = [
            'Body' => json_encode($this->mockedRegexPayload),
            'MD5OfBody' => md5(json_encode($this->mockedRegexPayload)),
            'ReceiptHandle' => $this->mockedReceiptHandle,
            'MessageId' => $this->mockedMessageId,
            'Attributes' => ['ApproximateReceiveCount' => 1],
        ];
    }

    /**
     * @test
     */
    public function fire_standard_job(): void
    {
        $job = $this->getJob();
        $handler = Mockery::mock(stdClass::class);
        $handler->shouldReceive('fire')->once()->with($job, ['data']);
        $job->getContainer()->shouldReceive('make')->once()->with('foo')->andReturn($handler);

        $job->fire();
    }

    /**
     * @test
     */
    public function fire_sns_job(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SNS_FROM,
                'arn' => 'arn:aws:sns:eu-south-1:000000000000:MyTopic',
                'listener' => 'App\Listeners\MyListener',
            ],
        ]);
        $this->mock('App\Listeners\MyListener', function (MockInterface $mock) {
            $mock->shouldReceive('handle')->with($this->mockedPlainSnsPayload);
        });
        $this->mockedSqsClient->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $this->mockedReceiptHandle,
        ]);

        $job = $this->getJob($this->mockedSnsJobData, $this->app);
        $job->fire();
    }

    /**
     * @test
     */
    public function fire_key_value_job(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD,
                'search' => [
                    'key' => 'key',
                    'value' => 'value',
                ],
                'listener' => 'App\Listeners\MyListener',
            ],
        ]);
        $this->mock('App\Listeners\MyListener', function (MockInterface $mock) {
            $mock->shouldReceive('handle')->with(['key' => 'value']);
        });
        $this->mockedSqsClient->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $this->mockedReceiptHandle,
        ]);

        $job = $this->getJob($this->mockedJobStrictKeyData, $this->app);
        $job->fire();
    }

    /**
     * @test
     */
    public function fire_key_value_job_with_regex_value(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SQS_REGEX_PAYLOAD,
                'search' => [
                    'key' => 'key',
                    'value' => '/^test-/',
                ],
                'listener' => 'App\Listeners\MyListener',
            ],
        ]);
        $this->mock('App\Listeners\MyListener', function (MockInterface $mock) {
            $mock->shouldReceive('handle')->with($this->mockedRegexPayload);
        });
        $this->mockedSqsClient->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $this->mockedReceiptHandle,
        ]);

        $job = $this->getJob($this->mockedJobRegexKeyData, $this->app);
        $job->fire();
    }

    /**
     * @test
     */
    public function fire_job_with_unmatched_payload(): void
    {
        LogFake::bind();

        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD,
                'search' => [
                    'key' => 'key',
                    'value' => 'test',
                ],
                'listener' => 'App\Listeners\MyListener',
            ],
        ]);
        Config::set('queue.connections.test.unmatched_listener', true);
        $job = $this->getJob($this->mockedJobRegexKeyData, $this->app);

        $this->mockedSqsClient->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $this->mockedReceiptHandle,
        ]);

        $job->fire();

        Log::assertLogged(function (LogEntry $log) {
            $encodedPayload = json_encode($this->mockedRegexPayload);

            return $log->level === 'info' &&
                $log->message === "Unmatched message for queue {$this->queueName}: {$encodedPayload}";
        });
    }

    /**
     * @test
     */
    public function fail_standard_job(): void
    {
        $this->mockedSqsClient->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $this->mockedReceiptHandle,
        ]);

        $this->mock('foo', function (MockInterface $mock) {
            $mock->shouldReceive('failed')->with(['data'], Mockery::type(ManuallyFailedException::class), Mockery::type('string'));
        });

        $job = $this->getJob(null, $this->app);
        $job->fail(new ManuallyFailedException('Manually failed'));
    }

    /**
     * @test
     */
    public function fail_key_value_job(): void
    {
        Config::set(
            "configurable-sqs.{$this->queueName}",
            [
                [
                    'type' => ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD,
                    'search' => [
                        'key' => 'key',
                        'value' => 'value',
                    ],
                    'listener' => 'App\Listeners\MyListener',
                ],
            ]
        );

        $this->mock('App\Listeners\MyListener', function (MockInterface $mock) {
            $mock->shouldReceive('failed')
                 ->with(
                     ['key' => 'value'],
                     Mockery::type(ManuallyFailedException::class),
                     $this->mockedMessageId
                 );
        });

        $this->mockedSqsClient->shouldReceive('deleteMessage')->once()->with([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $this->mockedReceiptHandle,
        ]);

        /** @var ConfigurableJob $job */
        $job = $this->getJob($this->mockedJobStrictKeyData, $this->app);

        $job->fail(new ManuallyFailedException('Manually failed'));
    }

    /**
     * @test
     */
    public function matched_config_tries_and_array_backoff_are_used(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD,
                'search' => ['key' => 'key', 'value' => 'value'],
                'listener' => 'App\Listeners\MyListener',
                'tries' => 4,
                'backoff' => [5, 30, 60],
            ],
        ]);
        $this->mock('App\Listeners\MyListener');

        $job = $this->getJob($this->mockedJobStrictKeyData, $this->app);

        $this->assertSame(4, $job->maxTries());
        $this->assertSame('5,30,60', $job->backoff());
    }

    /**
     * @test
     */
    public function matched_config_int_backoff_is_cast_to_string(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SNS_FROM,
                'arn' => 'arn:aws:sns:eu-south-1:000000000000:MyTopic',
                'listener' => 'App\Listeners\MyListener',
                'backoff' => 10,
            ],
        ]);
        $this->mock('App\Listeners\MyListener');

        $job = $this->getJob($this->mockedSnsJobData, $this->app);

        $this->assertNull($job->maxTries());
        $this->assertSame('10', $job->backoff());
    }

    /**
     * @test
     * @testWith ["0", 0]
     *           ["4", 4]
     *           ["", null]
     *           ["foo", null]
     * @param string $tries
     * @param ?int $expected
     */
    public function matched_config_string_tries_is_cast_or_falls_back(string $tries, ?int $expected): void
    {
        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD,
                'search' => ['key' => 'key', 'value' => 'value'],
                'listener' => 'App\Listeners\MyListener',
                'tries' => $tries,
            ],
        ]);
        $this->mock('App\Listeners\MyListener');

        $job = $this->getJob($this->mockedJobStrictKeyData, $this->app);

        $this->assertSame($expected, $job->maxTries());
    }

    /**
     * @test
     * @testWith [[], null]
     *           [[""], null]
     *           ["", null]
     *           ["foo", null]
     *           [[0], "0"]
     *           [[5, "x", "60"], "5,60"]
     * @param mixed $backoff
     * @param ?string $expected
     */
    public function matched_config_invalid_backoff_is_discarded(mixed $backoff, ?string $expected): void
    {
        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD,
                'search' => ['key' => 'key', 'value' => 'value'],
                'listener' => 'App\Listeners\MyListener',
                'backoff' => $backoff,
            ],
        ]);
        $this->mock('App\Listeners\MyListener');

        $job = $this->getJob($this->mockedJobStrictKeyData, $this->app);

        $this->assertSame($expected, $job->backoff());
    }

    /**
     * @test
     */
    public function matched_config_without_tries_and_backoff_falls_back_to_parent(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SQS_REGEX_PAYLOAD,
                'search' => ['key' => 'key', 'value' => '/^test-/'],
                'listener' => 'App\Listeners\MyListener',
            ],
        ]);
        $this->mock('App\Listeners\MyListener');

        $job = $this->getJob($this->mockedJobRegexKeyData, $this->app);

        $this->assertNull($job->maxTries());
        $this->assertNull($job->backoff());
    }

    /**
     * @test
     */
    public function unmatched_payload_ignores_listener_tries_and_backoff(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD,
                'search' => ['key' => 'key', 'value' => 'other'],
                'listener' => 'App\Listeners\MyListener',
                'tries' => 4,
                'backoff' => [5],
            ],
        ]);

        $job = $this->getJob($this->mockedJobStrictKeyData, $this->app);

        $this->assertNull($job->maxTries());
        $this->assertNull($job->backoff());
        $this->assertSame(ConfigurableJob::TYPE_SQS_UNMATCHED_PAYLOAD, $job->getJobType());
    }

    /**
     * @test
     */
    public function standard_laravel_job_uses_payload_tries_and_backoff(): void
    {
        $payload = json_encode(['job' => 'foo', 'data' => ['data'], 'maxTries' => 7, 'backoff' => '1,2']);
        $handler = Mockery::mock(stdClass::class);
        $job = $this->getJob(['Body' => $payload] + $this->mockedJobData);
        $job->getContainer()->shouldReceive('make')->once()->with('foo')->andReturn($handler);

        $this->assertSame(7, $job->maxTries());
        $this->assertSame('1,2', $job->backoff());
    }

    protected function getJob($jobdata = null, $app = null)
    {
        return new ConfigurableJob(
            $app ?? $this->mockedContainer,
            $this->mockedSqsClient,
            $jobdata ?? $this->mockedJobData,
            'test',
            $this->queueUrl
        );
    }
}
