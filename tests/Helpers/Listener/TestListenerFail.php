<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers\Listener;

use Coverzen\ConfigurableSqs\Tests\Helpers\Events\TestEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\ManuallyFailedException;

class TestListenerFail implements ShouldQueue
{
    use Queueable;

    /**
     * @param TestEvent $event
     * @throws ManuallyFailedException
     */
    public function handle(TestEvent $event): void
    {
        throw new ManuallyFailedException('Manually failed exception');
    }
}
