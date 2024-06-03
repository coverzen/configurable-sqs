<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Unit\Job;

use ArgumentCountError;
use Coverzen\ConfigurableSqs\Job\ListenerEvent;
use Coverzen\ConfigurableSqs\Tests\TestCase;

class ListenerEventTest extends TestCase
{
    /**
     * @test
     */
    public function listener_event_must_valued(): void
    {
        $this->expectException(ArgumentCountError::class);
        new ListenerEvent();
    }

    /**
     * @test
     */
    public function listener_event_constructor(): void
    {
        $listener = new ListenerEvent('testClass', 'testMethod');
        $this->assertSame('testClass', $listener->getClass());
        $this->assertSame('testMethod', $listener->getMethod());
    }
}
