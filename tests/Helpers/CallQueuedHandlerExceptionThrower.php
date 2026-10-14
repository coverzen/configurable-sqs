<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers;

use Illuminate\Database\Eloquent\ModelNotFoundException;

class CallQueuedHandlerExceptionThrower
{
    public $deleteWhenMissingModels = true;

    public function handle(): void
    {
    }

    public function __wakeup(): void
    {
        throw new ModelNotFoundException('Foo');
    }
}
