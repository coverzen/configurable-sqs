<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Unit\Job;

use Coverzen\ConfigurableSqs\Job\ConfigurableCallQueuedHandler;
use Coverzen\ConfigurableSqs\Job\ConfigurableJob;
use Coverzen\ConfigurableSqs\Job\LogMessageListener;
use Coverzen\ConfigurableSqs\Tests\Helpers\CallQueuedHandlerExceptionThrower;
use Coverzen\ConfigurableSqs\Tests\Helpers\CallQueuedHandlerTestJob;
use Coverzen\ConfigurableSqs\Tests\Helpers\CallQueuedHandlerTestJobWithMiddleware;
use Coverzen\ConfigurableSqs\Tests\TestCase;
use Exception;
use Illuminate\Bus\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use TiMacDonald\Log\LogEntry;
use TiMacDonald\Log\LogFake;

class ConfigurableCallQueuedHandlerTest extends TestCase
{
    /**
     * @test
     * @throws Exception
     */
    public function it_can_handle_a_job(): void
    {
        CallQueuedHandlerTestJob::$handled = false;

        $job = Mockery::mock(ConfigurableJob::class);
        $job->shouldReceive('hasFailed')->andReturn(false);
        $job->shouldReceive('isDeleted')->andReturn(false);
        $job->shouldReceive('isReleased')->andReturn(false);
        $job->shouldReceive('isDeletedOrReleased')->andReturn(false);
        $job->shouldReceive('getCommand')->andReturn(CallQueuedHandlerTestJob::class);
        $job->shouldReceive('getJobType')->andReturn(ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD);
        $job->shouldReceive('getHandlerPayload')->andReturn(['key' => 'value']);
        $job->shouldReceive('delete')->once();

        $instance = new ConfigurableCallQueuedHandler(new Dispatcher($this->app), $this->app);

        $instance->call($job, ['key' => 'value']);

        $this->assertTrue(CallQueuedHandlerTestJob::$handled);
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_handle_a_job_with_middleware(): void
    {
        CallQueuedHandlerTestJobWithMiddleware::$handled = false;
        CallQueuedHandlerTestJobWithMiddleware::$middlewareCommand = null;

        $job = Mockery::mock(ConfigurableJob::class);
        $job->shouldReceive('hasFailed')->andReturn(false);
        $job->shouldReceive('isDeleted')->andReturn(false);
        $job->shouldReceive('isReleased')->andReturn(false);
        $job->shouldReceive('isDeletedOrReleased')->andReturn(false);
        $job->shouldReceive('getCommand')->andReturn(CallQueuedHandlerTestJobWithMiddleware::class);
        $job->shouldReceive('getJobType')->andReturn(ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD);
        $job->shouldReceive('getHandlerPayload')->andReturn(['key' => 'value']);
        $job->shouldReceive('delete')->once();

        $instance = new ConfigurableCallQueuedHandler(new Dispatcher($this->app), $this->app);

        $instance->call($job, ['key' => 'value']);

        $this->assertInstanceOf(CallQueuedHandlerTestJobWithMiddleware::class, CallQueuedHandlerTestJobWithMiddleware::$middlewareCommand);
        $this->assertTrue(CallQueuedHandlerTestJobWithMiddleware::$handled);
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_handle_a_job_and_delete_when_missing_models(): void
    {
        Event::fake();

        $job = Mockery::mock(ConfigurableJob::class);
        $job->shouldReceive('hasFailed')->andReturn(false);
        $job->shouldReceive('isDeleted')->andReturn(false);
        $job->shouldReceive('isReleased')->andReturn(false);
        $job->shouldReceive('isDeletedOrReleased')->andReturn(false);
        $job->shouldReceive('getCommand')->andReturn(CallQueuedHandlerExceptionThrower::class);
        $job->shouldReceive('getJobType')->andReturn(ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD);
        $job->shouldReceive('getHandlerPayload')->andReturn(['key' => 'value']);
        $job->shouldReceive('delete')->once();

        $instance = new ConfigurableCallQueuedHandler(new Dispatcher($this->app), $this->app);

        $instance->call($job, ['key' => 'value']);

        Event::assertNotDispatched(JobFailed::class);
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_log_an_unmatched_payload(): void
    {
        LogFake::bind();

        $job = Mockery::mock(ConfigurableJob::class);
        $job->shouldReceive('hasFailed')->andReturn(false);
        $job->shouldReceive('isDeleted')->andReturn(false);
        $job->shouldReceive('isReleased')->andReturn(false);
        $job->shouldReceive('isDeletedOrReleased')->andReturn(false);
        $job->shouldReceive('getCommand')->andReturn(LogMessageListener::class);
        $job->shouldReceive('getJobType')->andReturn(ConfigurableJob::TYPE_SQS_UNMATCHED_PAYLOAD);
        $job->shouldReceive('payload')->andReturn(['key' => 'value']);
        $job->shouldReceive('getUnmatchedListener')->andReturn(true);
        $job->shouldReceive('getQueue')->andReturn('test-logger');
        $job->shouldReceive('delete')->once();

        $instance = new ConfigurableCallQueuedHandler(new Dispatcher($this->app), $this->app);

        $instance->call($job, ['key' => 'value']);

        Log::assertLogged(fn (LogEntry $log) => $log->level === 'info' &&
            $log->message === 'Unmatched message for queue test-logger: {"key":"value"}');
    }
}
