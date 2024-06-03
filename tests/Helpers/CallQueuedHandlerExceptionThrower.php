<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers;

use Illuminate\Database\Eloquent\ModelNotFoundException;

class CallQueuedHandlerExceptionThrower
{
    public $deleteWhenMissingModels = true;

    public function handle()
    {
    }

    public function __wakeup()
    {
        throw new ModelNotFoundException('Foo');
    }
}
