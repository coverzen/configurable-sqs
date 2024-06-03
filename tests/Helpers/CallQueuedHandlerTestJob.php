<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers;

use Illuminate\Queue\InteractsWithQueue;

class CallQueuedHandlerTestJob
{
    use InteractsWithQueue;

    public static bool $handled = false;
    public static array $data = [];

    public function handle($data): void
    {
        static::$handled = true;
        static::$data = $data;
    }
}
