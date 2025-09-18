<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers;

use Illuminate\Queue\InteractsWithQueue;

class CallQueuedHandlerTestJob
{
    use InteractsWithQueue;

    public static bool $handled = false;

    /** @var array<array-key, mixed> $data */
    public static array $data = [];

    /**
     * @param array<array-key, mixed> $data
     */
    public function handle(array $data): void
    {
        static::$handled = true;
        static::$data = $data;
    }
}
