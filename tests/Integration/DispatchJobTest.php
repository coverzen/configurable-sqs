<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Integration;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Coverzen\ConfigurableSqs\Job\ConfigurableJob;
use Coverzen\ConfigurableSqs\Sqs\ConfigurableConnector;
use Coverzen\ConfigurableSqs\Tests\Helpers\CallQueuedHandlerTestJob;
use Coverzen\ConfigurableSqs\Tests\Helpers\Events\TestEvent;
use Coverzen\ConfigurableSqs\Tests\Helpers\Listener\TestListener;
use Coverzen\ConfigurableSqs\Tests\Helpers\Listener\TestListenerFail;
use Coverzen\ConfigurableSqs\Tests\Helpers\Listener\TestListenerFilter;
use Coverzen\ConfigurableSqs\Tests\Helpers\Model\TestModel;
use Coverzen\ConfigurableSqs\Tests\TestCase;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

class DispatchJobTest extends TestCase
{
    private ?SqsClient $client = null;
    private ?SnsClient $snsClient = null;
    private string $queueName;

    protected function setUp(): void
    {
        parent::setUp();

        CallQueuedHandlerTestJob::$handled = false;
        CallQueuedHandlerTestJob::$data = [];

        Config::set('queue.default', 'test');

        $this->app->afterResolving(QueueManager::class, function (QueueManager $manager) {
            $manager->addConnector('configurable-sqs', fn () => new ConfigurableConnector());
        });

        $this->queueName = Config::get('queue.connections.test.queue');

        $this->client = new SqsClient([
            'region' => 'eu-south-1',
            'version' => '2012-11-05',
            'endpoint' => Config::get('queue.connections.test.endpoint'),
            'credentials' => [
                'key' => Config::get('queue.connections.test.key'),
                'secret' => Config::get('queue.connections.test.secret'),
            ],
        ]);

        $this->snsClient = new SnsClient([
            'region' => Config::get('queue.connections.test.region'),
            'version' => '2010-03-31',
            'endpoint' => Config::get('queue.connections.test.endpoint'),
            'credentials' => [
                'key' => Config::get('queue.connections.test.key'),
                'secret' => Config::get('queue.connections.test.secret'),
            ],
        ]);

        $this->client->createQueue([
            'QueueName' => $this->queueName,
        ]);

        $this->snsClient->createTopic([
            'Name' => 'test-topic',
        ]);

        $this->snsClient->subscribe([
            'Protocol' => 'sqs',
            'TopicArn' => 'arn:aws:sns:eu-south-1:000000000000:test-topic',
            'Endpoint' => 'arn:aws:sqs:eu-south-1:000000000000:' . $this->queueName,
        ]);
    }

    protected function tearDown(): void
    {
        $this->client->deleteQueue([
            'QueueUrl' => Config::get('queue.connections.test.prefix') . $this->queueName,
        ]);

        $this->snsClient->deleteTopic([
            'TopicArn' => 'arn:aws:sns:eu-south-1:000000000000:test-topic',
        ]);

        parent::tearDown(); // TODO: Change the autogenerated stub
    }

    /**
     * @test
     */
    public function dispatch_job(): void
    {
        $this->client->sendMessage([
            'QueueUrl' => Config::get('queue.connections.test.prefix') . $this->queueName,
            'MessageBody' => json_encode(['key' => 'test']),
        ]);

        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD,
                'search' => [
                    'key' => 'key',
                    'value' => 'test',
                ],
                'listener' => CallQueuedHandlerTestJob::class,
            ],
        ]);

        $this->artisan('queue:work', ['--once' => true]);
        $this->assertTrue(CallQueuedHandlerTestJob::$handled);
        $this->assertSame(['key' => 'test'], CallQueuedHandlerTestJob::$data);
    }

    /**
     * @test
     */
    public function dispatch_standard_job_without_filter(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", []);

        Event::listen(TestEvent::class, TestListener::class);

        TestEvent::dispatch(new TestModel('test'));

        $this->withoutMockingConsoleOutput()->artisan('queue:work', ['--once' => true, '-vvv' => true]);

        $this->assertMatchesRegularExpression('/^(.*?)TestListener (.*?) DONE\\n$/m', Artisan::output());
    }

    /**
     * @test
     */
    public function dispatch_standard_job_and_fail(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", []);

        Event::listen(TestEvent::class, TestListenerFail::class);

        TestEvent::dispatch(new TestModel('test'));

        $this->withoutMockingConsoleOutput()->artisan('queue:work', ['--once' => true, '-vvv' => true]);

        $this->assertMatchesRegularExpression('/^(.*?)TestListenerFail (.*?) FAIL\\n$/m', Artisan::output());
    }

    /**
     * @test
     */
    public function dispatch_standard_job_and_filter_true(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", []);

        Event::listen(TestEvent::class, TestListenerFilter::class);

        TestEvent::dispatch(new TestModel('test'));

        $this->withoutMockingConsoleOutput()->artisan('queue:work', ['--once' => true, '-vvv' => true]);

        $this->assertMatchesRegularExpression('/^(.*?)TestListenerFilter (.*?) DONE\\n$/m', Artisan::output());
    }

    /**
     * @test
     */
    public function dispatch_standard_job_and_filter_false(): void
    {
        Config::set("configurable-sqs.{$this->queueName}", []);

        Event::listen(TestEvent::class, TestListenerFilter::class);

        TestEvent::dispatch(new TestModel('test', false));

        $this->withoutMockingConsoleOutput()->artisan('queue:work', ['--once' => true, '-vvv' => true]);

        $this->assertStringNotContainsString('TestListenerFilter', Artisan::output());
    }

    /**
     * @test
     */
    public function dispatch_sns_job(): void
    {
        $this->snsClient->publish([
            'TopicArn' => 'arn:aws:sns:eu-south-1:000000000000:test-topic',
            'Message' => json_encode(['key' => 'sns-test']),
        ]);

        Config::set("configurable-sqs.{$this->queueName}", [
            [
                'type' => ConfigurableJob::TYPE_SNS_FROM,
                'arn' => 'arn:aws:sns:eu-south-1:000000000000:test-topic',
                'listener' => CallQueuedHandlerTestJob::class,
            ],
        ]);
        sleep(5); // wait for subscription (it's async in localstack
        $this->artisan('queue:work', ['--once' => true]);

        $this->assertTrue(CallQueuedHandlerTestJob::$handled);
        $this->assertSame(['key' => 'sns-test'], CallQueuedHandlerTestJob::$data);
    }
}
