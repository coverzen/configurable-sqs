<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers\Events;

use Coverzen\ConfigurableSqs\Tests\Helpers\Model\TestModel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     * @param TestModel $model
     */
    public function __construct(
        public TestModel $model,
    ) {
    }
}
