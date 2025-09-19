<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Unit\Job;

use Coverzen\ConfigurableSqs\Job\ConfigurableJob;
use Coverzen\ConfigurableSqs\Job\LogMessageListener;
use Coverzen\ConfigurableSqs\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use TiMacDonald\Log\LogEntry;
use TiMacDonald\Log\LogFake;

class LogMessageListenerTest extends TestCase
{
    private ConfigurableJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        LogFake::bind();
        $this->job = Mockery::mock(ConfigurableJob::class);
        $this->job->shouldReceive('getQueue')->andReturn('test-queue');
        $this->job->shouldReceive('payload')->andReturn(['test' => 'test']);
    }

    #[Test]
    public function log_message_listener_constructor_array_logger(): void
    {
        $logger = ['channel' => 'testChannel'];
        $listener = new LogMessageListener();
        $listener->handle($this->job, $logger);
        Log::channel('testChannel')->assertLogged(fn (LogEntry $log) => $log->level === 'info' &&
            $log->message === 'Unmatched message for queue test-queue: {"test":"test"}');
    }

    #[Test]
    public function log_message_listener_constructor_boolean_logger(): void
    {
        $listener = new LogMessageListener();
        $listener->handle($this->job, true);
        Log::assertLogged(fn (LogEntry $log) => $log->level === 'info' &&
            $log->message === 'Unmatched message for queue test-queue: {"test":"test"}');
    }
}
