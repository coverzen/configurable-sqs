<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Unit\Sqs;

use Aws\Result;
use Aws\Sqs\SqsClient;
use Coverzen\ConfigurableSqs\Job\ConfigurableJob;
use Coverzen\ConfigurableSqs\Sqs\ConfigurableQueue;
use Coverzen\ConfigurableSqs\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Mockery;

class ConfigurableQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->sqs = Mockery::mock(SqsClient::class);
        $this->mockedJob = 'foo';
        $this->mockedData = ['data'];
        $this->mockedPayload = json_encode(['job' => $this->mockedJob, 'data' => $this->mockedData]);
        $this->mockedDelay = 10;
        $this->mockedMessageId = 'e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81';
        $this->mockedReceiptHandle = '0NNAq8PwvXuWv5gMtS9DJ8qEdyiUwbAjpp45w2m6M4SJ1Y+PxCh7R930NRB8ylSacEmoSnW18bgd4nK\/O6ctE+VFVul4eD23mA07vVoSnPI4F\/voI1eNCp6Iax0ktGmhlNVzBwaZHEr91BRtqTRM3QKd2ASF8u+IQaSwyl\/DGK+P1+dqUOodvOVtExJwdyDLy1glZVgm85Yw9Jf5yZEEErqRwzYz\/qSigdvW4sm2l7e4phRol\/+IjMtovOyH\/ukueYdlVbQ4OshQLENhUKe7RNN5i6bE\/e5x9bnPhfj2gbM';

        $this->mockedSendMessageResponseModel = new Result([
            'Body' => $this->mockedPayload,
            'MD5OfBody' => md5($this->mockedPayload),
            'ReceiptHandle' => $this->mockedReceiptHandle,
            'MessageId' => $this->mockedMessageId,
            'Attributes' => ['ApproximateReceiveCount' => 1],
        ]);

        $this->mockedReceiveMessageResponseModel = new Result([
            'Messages' => [
                0 => [
                    'Body' => $this->mockedPayload,
                    'MD5OfBody' => md5($this->mockedPayload),
                    'ReceiptHandle' => $this->mockedReceiptHandle,
                    'MessageId' => $this->mockedMessageId,
                ],
            ],
        ]);

        $this->mockedReceiveEmptyMessageResponseModel = new Result([
            'Messages' => null,
        ]);

        $this->mockedQueueAttributesResponseModel = new Result([
            'Attributes' => [
                'ApproximateNumberOfMessages' => 1,
            ],
        ]);
    }

    /**
     * @test
     * @return void
     */
    public function it_can_pop_message(): void
    {
        $queue = new ConfigurableQueue(
            $this->sqs,
            Config::get('queue.connections.test.queue'),
            Config::get('queue.connections.test.prefix'),
        );

        $queue->setContainer($this->app);

        $this->sqs->shouldReceive('receiveMessage')
                  ->once()
                  ->with([
                      'QueueUrl' => Config::get('queue.connections.test.prefix') . Config::get('queue.connections.test.queue'),
                      'AttributeNames' => ['ApproximateReceiveCount'],
                  ])
                  ->andReturn($this->mockedReceiveMessageResponseModel);

        $result = $queue->pop(Config::get('queue.connections.test.queue'));
        $this->assertInstanceOf(ConfigurableJob::class, $result);
    }

    /**
     * @test
     * @return void
     */
    public function it_can_pop_empty_message(): void
    {
        $queue = new ConfigurableQueue(
            $this->sqs,
            Config::get('queue.connections.test.queue'),
            Config::get('queue.connections.test.prefix'),
        );

        $queue->setContainer($this->app);

        $this->sqs->shouldReceive('receiveMessage')
                  ->once()
                  ->with([
                      'QueueUrl' => Config::get('queue.connections.test.prefix') . Config::get('queue.connections.test.queue'),
                      'AttributeNames' => ['ApproximateReceiveCount'],
                  ])
                  ->andReturn($this->mockedReceiveEmptyMessageResponseModel);

        $result = $queue->pop(Config::get('queue.connections.test.queue'));
        $this->assertNull($result);
    }
}
