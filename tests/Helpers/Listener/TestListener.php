<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers\Listener;

use Coverzen\ConfigurableSqs\Tests\Helpers\Events\TestEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class TestListener implements ShouldQueue
{
    use Queueable;

    public static function enqueueFilter(TestEvent $event): bool
    {
        return false;
    }

    public function handle(TestEvent $event): bool
    {
        return true;
    }
}