<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers;

abstract class AbstractCallQueuedHandlerTestJobWithMiddleware
{
    public static $middlewareCommand;

    public function middleware(): array
    {
        return [
            new class {
                public function handle($command, $next)
                {
                    AbstractCallQueuedHandlerTestJobWithMiddleware::$middlewareCommand = $command;

                    return $next($command);
                }
            },
        ];
    }
}
